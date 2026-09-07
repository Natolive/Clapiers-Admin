<?php

namespace App\Common\Service;

use App\Repository\SettingRepository;

/**
 * Configuration Bunny Storage, entièrement en administration (table `setting`) :
 * plus aucune variable d'environnement. Point d'entrée unique — le stockage
 * ({@see MemberMediaStorage}) ne lit rien d'autre. Une valeur absente vaut
 * chaîne vide, et une zone non configurée fait échouer proprement les uploads
 * et téléchargements (502) au lieu d'envoyer les fichiers dans le vide.
 */
class BunnyConfigProvider
{
    /** Champ exposé => nom du réglage en base. */
    public const KEYS = [
        'storageUrl' => 'bunny_storage_url',
        'storageKey' => 'bunny_storage_key',
    ];

    public function __construct(
        private readonly SettingRepository $settingRepository,
    ) {
    }

    /** @param key-of<self::KEYS> $field */
    public function get(string $field): string
    {
        return $this->settingRepository->get(self::KEYS[$field]) ?? '';
    }

    /**
     * Vue admin : la clé d'accès n'est jamais renvoyée — seule son existence.
     *
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return [
            'storageUrl' => $this->get('storageUrl'),
            'storageKeyDefined' => $this->get('storageKey') !== '',
        ];
    }

    /**
     * Enregistre les champs fournis (null ou vide = inchangé).
     *
     * @param array<string, string|null> $changes
     */
    public function update(array $changes): void
    {
        foreach (self::KEYS as $field => $settingName) {
            $value = $changes[$field] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $this->settingRepository->set($settingName, $value);
        }
    }
}
