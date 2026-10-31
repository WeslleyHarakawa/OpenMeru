<?php

namespace App\Support;

/**
 * Reads and writes KEY=value lines of a .env file without touching anything
 * else in it (comments, order, keys it does not know about).
 */
class EnvFile
{
    public function __construct(private readonly string $path) {}

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
        if (! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($this->path), $m)) {
            return null;
        }

        return self::unquote(trim($m[1]));
    }

    /** @param  array<string, string|int|null>  $values */
    public function set(array $values): void
    {
        $text = $this->exists() ? (string) file_get_contents($this->path) : '';
        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote((string) $value);
            $text = preg_match('/^'.preg_quote($key, '/').'=.*$/m', $text)
                ? preg_replace('/^'.preg_quote($key, '/').'=.*$/m', self::literal($line), $text, 1)
                : rtrim($text, "\n")."\n".$line."\n";
        }
        file_put_contents($this->path, $text);
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@+,=-]+$/', $value)) {
            return $value;
        }

        return '"'.addcslashes($value, "\\\"\$\n").'"';
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            return stripcslashes(substr($value, 1, -1));
        }
        if (strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /** Escapes a replacement string for preg_replace ($1 and \ must stay literal). */
    private static function literal(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '$' => '\\$']);
    }
}
