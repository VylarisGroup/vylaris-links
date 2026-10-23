<?php

namespace App\Support\Updater;

final class Release
{
    public function __construct(
        public readonly string $tag,
        public readonly string $version,
        public readonly string $name,
        public readonly string $notes,
        public readonly ?string $publishedAt,
        public readonly string $url,
        public readonly string $assetUrl,
        public readonly int $assetSize,
        public readonly ?string $digest,
    ) {
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(?array $a): ?self
    {
        if (! $a || empty($a['assetUrl'])) {
            return null;
        }

        return new self(
            $a['tag'], $a['version'], $a['name'] ?? $a['tag'], $a['notes'] ?? '', $a['publishedAt'] ?? null,
            $a['url'] ?? '', $a['assetUrl'], (int) ($a['assetSize'] ?? 0), $a['digest'] ?? null
        );
    }
}
