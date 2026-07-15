<?php

namespace App\Common\Service;

/**
 * Résout la saison sportive courante au format "AAAA-AAAA".
 * La saison bascule en septembre (ex. le 30/06/2026 → "2025-2026",
 * le 01/09/2026 → "2026-2027").
 */
class SeasonResolver
{
    private const SEASON_START_MONTH = 9;

    public function current(): string
    {
        return $this->forDate(new \DateTimeImmutable('now'));
    }

    public function forDate(\DateTimeImmutable $date): string
    {
        $year = (int) $date->format('Y');
        $startYear = ((int) $date->format('n')) >= self::SEASON_START_MONTH ? $year : $year - 1;

        return sprintf('%d-%d', $startYear, $startYear + 1);
    }
}
