@php
    use App\Support\Embed;
    use App\Support\Platforms;
    use Illuminate\Support\Carbon;
@endphp
@switch($item->type)
    @case('link')
        @if ($item->url)
            <a class="item link fx-{{ $item->effect }}" href="{{ $preview ? \App\Support\UrlGuard::clean($item->url) : route('go', $item->id) }}" rel="nofollow noopener ugc" @if (! $preview) target="_blank" @endif>
                @if ($item->icon)<span class="ico" style="--ic:{{ Platforms::color($item->icon) }}" aria-hidden="true">{{ Platforms::badge($item->icon) }}</span>@endif
                <span class="t">{{ $item->title ?: $item->url }}</span>
                @if ($item->isProtected())<span class="lock">{{ $item->age_gate && ! $item->password_hash ? '18+' : 'Geschützt' }}</span>@endif
            </a>
        @endif
        @break
    @case('heading')
        <h2 class="item heading">{{ $item->title }}</h2>
        @break
    @case('text')
        @if ($item->body)<p class="item text">{{ $item->body }}</p>@endif
        @break
    @case('image')
        @if ($item->url && \App\Support\UrlGuard::isValid($item->url, ['http', 'https']))
            <figure class="item image">
                <img src="{{ $item->url }}" alt="{{ $item->title }}" loading="lazy" referrerpolicy="no-referrer">
                @if ($item->title)<figcaption>{{ $item->title }}</figcaption>@endif
            </figure>
        @endif
        @break
    @case('youtube')
        @if ($id = Embed::youtubeId($item->url))
            <div class="item embed yt"><iframe src="https://www.youtube-nocookie.com/embed/{{ $id }}" title="{{ $item->title ?: 'YouTube-Video' }}" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>
        @endif
        @break
    @case('twitch')
        @if ($ch = Embed::twitchChannel($item->url))
            <div class="item embed tw"><iframe src="https://player.twitch.tv/?channel={{ $ch }}&parent={{ request()->getHost() }}&muted=true" title="{{ $item->title ?: 'Twitch' }}" loading="lazy" allowfullscreen></iframe></div>
        @endif
        @break
    @case('spotify')
        @if ($sp = Embed::spotifyPath($item->url))
            <div class="item embed sp"><iframe src="https://open.spotify.com/embed/{{ $sp }}" height="152" title="{{ $item->title ?: 'Spotify' }}" loading="lazy" allow="encrypted-media"></iframe></div>
        @endif
        @break
    @case('countdown')
        @php $target = ! empty($item->data['target']) ? Carbon::parse($item->data['target'], config('app.timezone')) : null; @endphp
        @if ($target)
            <div class="item countdown" data-target="{{ $target->toIso8601String() }}">
                <div class="cd-title">{{ $item->title }}</div>
                <div class="cd-grid" role="timer" aria-live="off">
                    <div><b data-u="d">0</b><span>Tage</span></div><div><b data-u="h">0</b><span>Std</span></div>
                    <div><b data-u="m">0</b><span>Min</span></div><div><b data-u="s">0</b><span>Sek</span></div>
                </div>
                <noscript><p>{{ $target->translatedFormat('d.m.Y H:i') }}</p></noscript>
            </div>
        @endif
        @break
    @case('gallery')
        @php $imgs = array_values(array_filter((array) ($item->data['images'] ?? []), fn ($u) => \App\Support\UrlGuard::isValid($u, ['http', 'https']))); @endphp
        @if ($imgs)
            <div class="item gallery" tabindex="0" aria-label="{{ $item->title ?: 'Bildergalerie' }}">
                @foreach ($imgs as $u)<img src="{{ $u }}" alt="" loading="lazy" referrerpolicy="no-referrer">@endforeach
            </div>
        @endif
        @break
@endswitch
