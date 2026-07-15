<?php

namespace App\Common\Service;

use App\Repository\SettingRepository;

/**
 * Fournit la saison sportive courante. Priorité au réglage défini en
 * administration ; à défaut, on calcule la saison depuis la date (SeasonResolver).
 * Point d'entrée unique : tout endroit qui a besoin de "la saison" passe par ici.
 */
class SeasonProvider
{
    public const SETTING_KEY = 'current_season';

    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly SeasonResolver $seasonResolver,
    ) {
    }

    /** La saison courante retenue (réglage admin, sinon calcul par date). */
    public function current(): string
    {
        return $this->settingRepository->get(self::SETTING_KEY) ?? $this->seasonResolver->current();
    }

    /** La saison calculée depuis la date du jour (valeur suggérée par défaut). */
    public function computed(): string
    {
        return $this->seasonResolver->current();
    }

    public function set(string $season): void
    {
        $this->settingRepository->set(self::SETTING_KEY, $season);
    }
}
