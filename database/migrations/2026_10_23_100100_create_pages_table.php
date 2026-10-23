<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 40)->unique();
            $table->string('title', 80);
            $table->string('bio', 280)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('background_path')->nullable();
            $table->string('theme', 30)->default('vylaris');
            $table->string('layout', 30)->default('buttons');
            $table->string('avatar_ring', 20)->default('none');
            $table->string('particles', 20)->default('none');
            $table->boolean('cursor_sparks')->default(false);
            $table->boolean('entrance_enabled')->default(false);
            $table->string('entrance_text', 80)->nullable();
            $table->string('music_url')->nullable();
            $table->boolean('hide_branding')->default(false);
            $table->boolean('contact_enabled')->default(false);
            $table->json('colors')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 30);
            $table->string('url', 500);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('link');
            $table->string('title', 120)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('icon', 30)->nullable();
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('effect', 20)->default('none');
            $table->boolean('is_visible')->default(true);
            $table->json('schedule')->nullable();
            $table->string('password_hash')->nullable();
            $table->boolean('age_gate')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['page_id', 'position']);
        });

        Schema::create('stat_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->default(0); // 0 = Seitenaufruf
            $table->date('day');
            $table->string('kind', 10);
            $table->string('device', 10)->default('other');
            $table->string('source', 60)->default('direkt');
            $table->string('country', 2)->nullable();
            $table->unsignedInteger('count')->default(0);
            $table->unique(['page_id', 'item_id', 'day', 'kind', 'device', 'source'], 'stat_days_unique');
            $table->index(['page_id', 'day']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('email', 120)->nullable();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('status', 15)->default('open');
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['reports', 'messages', 'stat_days', 'items', 'socials', 'pages'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
