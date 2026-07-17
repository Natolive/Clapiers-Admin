<?php

namespace App\Common\Service;

use App\Repository\SettingRepository;

/**
 * Indique si les inscriptions (demandes de licence) sont ouvertes. Réglage
 * défini en administration ; ouvert par défaut tant qu'il n'est pas explicitement
 * fermé. Point d'entrée unique pour tout endroit qui a besoin du statut.
 */
class InscriptionsStatusProvider
{
    public const SETTING_KEY = 'inscriptions_open';

    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /** Ouvert par défaut : seul le réglage explicite « 0 » ferme les inscriptions. */
    public function isOpen(): bool
    {
        return '0' !== $this->settingRepository->get(self::SETTING_KEY);
    }

    public function setOpen(bool $open): void
    {
        $this->settingRepository->set(self::SETTING_KEY, $open ? '1' : '0');
    }
}
