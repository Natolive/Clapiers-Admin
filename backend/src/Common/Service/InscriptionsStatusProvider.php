<?php

namespace App\Common\Service;

use App\Repository\SettingRepository;

/**
 * Statut des inscriptions, en deux réglages indépendants :
 *  - `open` : l'affichage « inscriptions ouvertes / clôturées » du site public,
 *    purement indicatif (badge d'accueil) ;
 *  - `formOpen` : la réception réelle des demandes (formulaire + API publique).
 *
 * Les deux sont ouverts par défaut tant qu'ils ne sont pas explicitement fermés.
 * Point d'entrée unique pour tout endroit qui a besoin du statut.
 */
class InscriptionsStatusProvider
{
    public const SETTING_KEY = 'inscriptions_open';
    public const FORM_SETTING_KEY = 'inscriptions_form_open';

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

    /** La demande de licence en ligne est-elle réellement acceptée ? */
    public function isFormOpen(): bool
    {
        return '0' !== $this->settingRepository->get(self::FORM_SETTING_KEY);
    }

    public function setFormOpen(bool $open): void
    {
        $this->settingRepository->set(self::FORM_SETTING_KEY, $open ? '1' : '0');
    }
}
