<?php

declare(strict_types=1);

namespace Ecf\Services;

/**
 * Genera l'elenco di slot orari ("HH:MM") per il tipo di campo `time_slot`
 * a partire da ora inizio/fine e step in minuti. Condiviso da FormRenderer
 * (rendering delle <option>) e FormValidator (verifica del valore inviato),
 * così i due producono sempre lo stesso insieme.
 */
final class TimeSlotGenerator
{
    public const DEFAULT_START = '08:00';
    public const DEFAULT_END = '17:00';
    public const DEFAULT_STEP = 30;
    public const ALLOWED_STEPS = [15, 30];

    /**
     * @return string[] slot "HH:MM", estremi inclusi. Vuoto se i parametri
     *                   non formano un intervallo valido (start > end, orario malformato).
     */
    public static function generate(mixed $start, mixed $end, mixed $step): array
    {
        $start = is_string($start) && $start !== '' ? $start : self::DEFAULT_START;
        $end = is_string($end) && $end !== '' ? $end : self::DEFAULT_END;
        $step = is_numeric($step) && in_array((int) $step, self::ALLOWED_STEPS, true)
            ? (int) $step
            : self::DEFAULT_STEP;

        $startMinutes = self::toMinutes($start);
        $endMinutes = self::toMinutes($end);

        if ($startMinutes === null || $endMinutes === null || $startMinutes > $endMinutes) {
            return [];
        }

        $slots = [];
        for ($m = $startMinutes; $m <= $endMinutes; $m += $step) {
            $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $slots;
    }

    private static function toMinutes(string $time): ?int
    {
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
