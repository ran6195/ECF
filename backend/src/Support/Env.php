<?php

declare(strict_types=1);

namespace Ecf\Support;

/**
 * Accesso semplice alle variabili d'ambiente caricate da phpdotenv.
 * Gestisce correttamente i valori vuoti (es. password DB vuota).
 */
final class Env
{
    public static function get(string $key, ?string $default = null): ?string
    {
        // phpdotenv popola $_ENV e $_SERVER; getenv() come fallback.
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        // Normalizza i casi speciali tipici dei file .env.
        return match (strtolower((string) $value)) {
            '(null)' => null,
            default => (string) $value,
        };
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }
}
