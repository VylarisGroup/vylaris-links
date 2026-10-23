<?php

namespace App\Support;

/**
 * Liest und schreibt eine .env-Datei, ohne Kommentare oder andere Zeilen zu verändern.
 */
class EnvFile
{
    public function __construct(private string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }
        if (! preg_match('/^[ \t]*'.preg_quote($key, '/').'[ \t]*=[ \t]*(.*?)[ \t]*$/m', (string) file_get_contents($this->path), $m)) {
            return null;
        }
        $v = $m[1];

        if ($v !== '' && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0] && strlen($v) >= 2) {
            $inner = substr($v, 1, -1);

            return $v[0] === '"' ? strtr($inner, ['\\"' => '"', '\\\\' => '\\', '\\$' => '$']) : $inner;
        }

        return trim(preg_replace('/[ \t]+#.*$/', '', $v));
    }

    public function set(string $key, ?string $value): void
    {
        $this->setMany([$key => $value]);
    }

    /** @param array<string, string|null> $pairs */
    public function setMany(array $pairs): void
    {
        $content = $this->exists() ? (string) file_get_contents($this->path) : '';
        $created = ! $this->exists();

        foreach ($pairs as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new \InvalidArgumentException("Ungültiger Schlüssel: {$key}");
            }
            $line = $key.'='.self::format((string) $value);
            $pattern = '/^[ \t]*'.preg_quote($key, '/').'[ \t]*=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, fn () => $line, $content, 1);
            } else {
                $content = rtrim($content, "\r\n").($content === '' ? '' : "\n").$line."\n";
            }
        }

        if (file_put_contents($this->path, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Die Datei '.$this->path.' ist nicht beschreibbar.');
        }
        if ($created) {
            @chmod($this->path, 0600);
        }
    }

    public static function format(string $value): string
    {
        if (preg_match('/[\r\n\0]/', $value)) {
            throw new \InvalidArgumentException('Werte dürfen keine Zeilenumbrüche enthalten.');
        }
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-,]+$/', $value)) {
            return $value;
        }

        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';
    }
}
