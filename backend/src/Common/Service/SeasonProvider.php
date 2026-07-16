<?php

namespace App\Common\Service;

use App\Repository\SeasonRepository;
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
        private readonly SeasonRepository $seasonRepository,
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
        $this->seasonRepository->ensure($season);
    }

    /**
     * Saisons proposables (celles enregistrées), la saison courante toujours
     * présente en tête si elle n'a pas encore été enregistrée.
     *
     * @return string[] plus récente d'abord
     */
    public function all(): array
    {
        $seasons = $this->seasonRepository->findAllNames();
        $current = $this->current();
        if (!in_array($current, $seasons, true)) {
            array_unshift($seasons, $current);
        }

        return $seasons;
    }
}
