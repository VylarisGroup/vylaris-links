<?php

namespace Tests\Feature;

use App\Mail\WeeklyReport;
use App\Models\Item;
use App\Models\Message;
use App\Models\Page;
use App\Models\Report;
use App\Models\StatDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LinksTest extends TestCase
{
    use RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function page(?User $user = null, array $attrs = []): Page
    {
        $user ??= User::factory()->create();

        return $user->pages()->create($attrs + ['slug' => 'demo', 'title' => 'Demo Seite']);
    }

    private function item(Page $page, array $attrs = []): Item
    {
        $hash = $attrs['password_hash'] ?? null;
        unset($attrs['password_hash']);
        $item = new Item($attrs + ['type' => 'link', 'title' => 'Mein Link', 'url' => 'https://example.com/ziel', 'position' => 1]);
        $item->page_id = $page->id;
        $item->password_hash = $hash;
        $item->save();

        return $item;
    }

    public function test_landing_and_auth_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Alle deine Links');
        $this->get('/anmelden')->assertOk();
        $this->get('/registrieren')->assertOk();
    }

    public function test_registration_logs_in_and_validates_password(): void
    {
        $this->post('/registrieren', ['name' => 'Anna', 'email' => 'anna@example.ch', 'password' => 'kurz', 'password_confirmation' => 'kurz', 'terms' => '1'])
            ->assertSessionHasErrors('password');

        $this->post('/registrieren', ['name' => 'Anna', 'email' => 'anna@example.ch', 'password' => 'sicheres-pw-123', 'password_confirmation' => 'sicheres-pw-123', 'terms' => '1'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_blocked_user_cannot_login(): void
    {
        $u = User::factory()->create(['password' => 'sicheres-pw-123']);
        $u->forceFill(['blocked_at' => now()])->save();

        $this->post('/anmelden', ['email' => $u->email, 'password' => 'sicheres-pw-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_create_page_rejects_reserved_and_duplicate_slugs(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post('/dashboard/seiten', ['slug' => 'admin', 'title' => 'X'])->assertSessionHasErrors('slug');
        $this->actingAs($u)->post('/dashboard/seiten', ['slug' => 'Gross Klein', 'title' => 'X'])->assertSessionHasErrors('slug');
        $this->actingAs($u)->post('/dashboard/seiten', ['slug' => 'meine-seite', 'title' => 'Meine Seite'])->assertRedirect();
        $this->actingAs(User::factory()->create())->post('/dashboard/seiten', ['slug' => 'meine-seite', 'title' => 'Y'])->assertSessionHasErrors('slug');
    }

    public function test_page_limit_is_enforced(): void
    {
        $u = User::factory()->create();
        foreach (['aaa', 'bbb', 'ccc'] as $s) {
            $this->page($u, ['slug' => $s]);
        }
        $this->actingAs($u)->post('/dashboard/seiten', ['slug' => 'ddd', 'title' => 'D'])->assertSessionHasErrors('slug');
        $this->assertSame(3, $u->pages()->count());
    }

    public function test_public_profile_renders_and_sets_no_cookies(): void
    {
        $page = $this->page(null, ['bio' => 'Hallo Welt']);
        $this->item($page);

        $res = $this->withHeader('User-Agent', self::UA)->get('/demo');
        $res->assertOk()->assertSee('Demo Seite')->assertSee('Mein Link')->assertSee('Hallo Welt');
        $this->assertEmpty($res->headers->getCookies(), 'Profilseiten dürfen keine Cookies setzen.');
        $this->assertSame(1, (int) StatDay::where('kind', 'view')->sum('count'));
    }

    public function test_unpublished_and_blocked_pages_are_hidden(): void
    {
        $page = $this->page(null, ['is_published' => false]);
        $this->get('/demo')->assertNotFound();

        $page->update(['is_published' => true]);
        $page->forceFill(['blocked_at' => now()])->save();
        $this->get('/demo')->assertNotFound();
    }

    public function test_bots_are_not_counted(): void
    {
        $this->page();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/demo')->assertOk();
        $this->assertSame(0, StatDay::count());
    }

    public function test_dangerous_urls_are_rejected(): void
    {
        $u = User::factory()->create();
        $page = $this->page($u);
        $item = $this->item($page);

        foreach (['javascript:alert(1)', 'data:text/html,<script>1</script>', 'vbscript:x', 'file:///etc/passwd'] as $bad) {
            $this->actingAs($u)->patchJson("/editor/{$page->id}/eintraege/{$item->id}", ['url' => $bad])->assertStatus(422);
        }
        $this->actingAs($u)->patchJson("/editor/{$page->id}/eintraege/{$item->id}", ['url' => 'beispiel.ch/seite'])
            ->assertOk()->assertJsonPath('item.url', 'https://beispiel.ch/seite');
    }

    public function test_click_redirects_and_counts(): void
    {
        $page = $this->page();
        $item = $this->item($page);

        $this->withHeader('User-Agent', self::UA)->get("/go/{$item->id}")->assertRedirect('https://example.com/ziel');
        $this->assertSame(1, (int) StatDay::where('kind', 'click')->where('item_id', $item->id)->sum('count'));
    }

    public function test_protected_link_needs_password_and_age_confirmation(): void
    {
        $page = $this->page();
        $item = $this->item($page, ['password_hash' => Hash::make('geheim123'), 'age_gate' => true]);

        $this->get("/go/{$item->id}")->assertOk()->assertSee('Geschützter Link')->assertDontSee('example.com/ziel');
        $this->post("/go/{$item->id}", ['password' => 'falsch', 'adult' => '1'])->assertStatus(422);
        $this->post("/go/{$item->id}", ['password' => 'geheim123'])->assertStatus(422); // ohne 18+
        $this->post("/go/{$item->id}", ['password' => 'geheim123', 'adult' => '1'])->assertRedirect('https://example.com/ziel');
    }

    public function test_profile_never_exposes_url_of_protected_links(): void
    {
        $page = $this->page();
        $this->item($page, ['url' => 'https://geheim.example/x', 'password_hash' => Hash::make('geheim123')]);

        $this->get('/demo')->assertOk()->assertDontSee('geheim.example');
    }

    public function test_schedule_hides_links_outside_window(): void
    {
        $page = $this->page();
        $this->item($page, ['title' => 'Nur Freitag Abend', 'schedule' => ['enabled' => true, 'days' => [5], 'from' => '18:00', 'to' => '23:00']]);

        Carbon::setTestNow(Carbon::parse('2026-10-23 12:00', 'Europe/Zurich')); // Freitag Mittag
        $this->get('/demo')->assertOk()->assertDontSee('Nur Freitag Abend');

        Carbon::setTestNow(Carbon::parse('2026-10-23 19:30', 'Europe/Zurich')); // Freitag Abend
        $this->get('/demo')->assertOk()->assertSee('Nur Freitag Abend');

        Carbon::setTestNow(Carbon::parse('2026-10-24 19:30', 'Europe/Zurich')); // Samstag
        $this->get('/demo')->assertOk()->assertDontSee('Nur Freitag Abend');
        Carbon::setTestNow();
    }

    public function test_contact_form_honeypot_filter_and_storage(): void
    {
        $page = $this->page(null, ['contact_enabled' => true]);

        $this->post('/demo/kontakt', ['name' => 'Bot', 'body' => 'Spam Spam Spam', 'website' => 'http://x.y'])->assertRedirect();
        $this->assertSame(0, Message::count());

        $this->post('/demo/kontakt', ['name' => 'Spam', 'body' => 'http://a.ch http://b.ch http://c.ch'])->assertRedirect();
        $this->assertSame(0, Message::count());

        $this->post('/demo/kontakt', ['name' => 'Eva', 'email' => 'eva@example.ch', 'body' => 'Hallo, tolle Seite!'])->assertRedirect();
        $this->assertSame(1, Message::count());

        $page->update(['contact_enabled' => false]);
        $this->post('/demo/kontakt', ['name' => 'Eva', 'body' => 'Hallo nochmal'])->assertNotFound();
    }

    public function test_report_and_admin_moderation(): void
    {
        $page = $this->page();
        $this->post('/demo/melden', ['reason' => 'spam', 'details' => 'Betrug'])->assertRedirect();
        $this->assertSame(1, Report::where('status', 'open')->count());

        $normal = User::factory()->create();
        $this->actingAs($normal)->get('/admin')->assertForbidden();
        $this->actingAs($normal)->post("/admin/seiten/{$page->id}/sperren")->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('/demo');
        $this->actingAs($admin)->post("/admin/seiten/{$page->id}/sperren", ['reason' => 'Spam'])->assertRedirect();

        $this->assertNotNull($page->fresh()->blocked_at);
        $this->assertSame(0, Report::where('status', 'open')->count());
        $this->get('/demo')->assertNotFound();

        $this->actingAs($admin)->post("/admin/seiten/{$page->id}/freigeben")->assertRedirect();
        $this->get('/demo')->assertOk();
    }

    public function test_users_cannot_touch_other_users_pages(): void
    {
        $page = $this->page();
        $item = $this->item($page);
        $other = User::factory()->create();

        $this->actingAs($other)->get("/editor/{$page->id}")->assertNotFound();
        $this->actingAs($other)->patchJson("/editor/{$page->id}", ['title' => 'Hacked'])->assertNotFound();
        $this->actingAs($other)->deleteJson("/editor/{$page->id}/eintraege/{$item->id}")->assertNotFound();
        $this->assertSame('Demo Seite', $page->fresh()->title);
    }

    public function test_editor_page_and_preview_render_for_owner(): void
    {
        $u = User::factory()->create();
        $page = $this->page($u);
        $this->item($page);

        $this->actingAs($u)->get("/editor/{$page->id}")->assertOk()->assertSee('Live-Vorschau');
        $this->actingAs($u)->get("/editor/{$page->id}/vorschau")->assertOk()->assertSee('Vorschau');
    }

    public function test_item_crud_and_reorder(): void
    {
        $u = User::factory()->create();
        $page = $this->page($u);

        $a = $this->actingAs($u)->postJson("/editor/{$page->id}/eintraege", ['type' => 'link'])->assertOk()->json('item.id');
        $b = $this->actingAs($u)->postJson("/editor/{$page->id}/eintraege", ['type' => 'heading'])->assertOk()->json('item.id');
        $this->actingAs($u)->postJson("/editor/{$page->id}/eintraege", ['type' => 'virus'])->assertStatus(422);

        $this->actingAs($u)->postJson("/editor/{$page->id}/eintraege/reihenfolge", ['ids' => [$b, $a]])->assertOk();
        $this->assertSame([$b, $a], $page->items()->pluck('id')->all());

        $this->actingAs($u)->deleteJson("/editor/{$page->id}/eintraege/{$a}")->assertOk();
        $this->assertSame(1, $page->items()->count());
    }

    public function test_embeds_are_parsed_safely(): void
    {
        $u = User::factory()->create();
        $page = $this->page($u);
        $yt = $this->item($page, ['type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Video']);
        $this->actingAs($u)->patchJson("/editor/{$page->id}/eintraege/{$yt->id}", ['url' => 'https://evil.example/watch'])->assertStatus(422);

        $this->get('/demo')->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_html_in_user_content_is_escaped(): void
    {
        $page = $this->page(null, ['title' => '<script>alert(1)</script>', 'bio' => '<img src=x onerror=alert(1)>']);
        $this->item($page, ['title' => '<b>fett</b>']);

        $html = $this->get('/demo')->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;b&gt;fett&lt;/b&gt;', $html);
    }

    public function test_weekly_report_goes_to_opted_in_users_only(): void
    {
        Mail::fake();
        $on = User::factory()->create(['weekly_report' => true]);
        $off = User::factory()->create(['weekly_report' => false]);
        $this->page($on, ['slug' => 'eins']);
        $this->page($off, ['slug' => 'zwei']);

        $this->artisan('links:weekly-report')->assertSuccessful();

        Mail::assertSent(WeeklyReport::class, 1);
        Mail::assertSent(WeeklyReport::class, fn ($m) => $m->hasTo($on->email));
    }

    public function test_slug_route_does_not_swallow_app_routes(): void
    {
        $this->get('/anmelden')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/gibt-es-nicht')->assertNotFound();
    }

    public function test_avatar_upload_rejects_non_images(): void
    {
        \Storage::fake('public');
        $u = User::factory()->create();
        $page = $this->page($u);

        $this->actingAs($u)->post("/editor/{$page->id}/upload/avatar", ['file' => \Illuminate\Http\UploadedFile::fake()->create('x.php', 10, 'application/x-php')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->actingAs($u)->post("/editor/{$page->id}/upload/avatar", ['file' => \Illuminate\Http\UploadedFile::fake()->image('a.png', 200, 200)], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotNull($page->fresh()->avatar_path);
    }
}
