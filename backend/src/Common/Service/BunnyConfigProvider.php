<?php

namespace App\Common\Service;

use App\Repository\SettingRepository;

/**
 * Configuration Bunny (Storage + pull zone CDN), entièrement en administration
 * (table `setting`) :
 * plus aucune variable d'environnement. Point d'entrée unique — le stockage
 * ({@see MemberMediaStorage}) ne lit rien d'autre. Une valeur absente vaut
 * chaîne vide, et une zone non configurée fait échouer proprement les uploads
 * et téléchargements (502) au lieu d'envoyer les fichiers dans le vide.
 *
 * `cdnUrl` / `tokenKey` décrivent la pull zone CDN et sa Token Authentication :
 * ils sont saisissables dès maintenant, le stockage s'en servira pour servir
 * les fichiers au navigateur.
 */
class BunnyConfigProvider
{
    /** Champ exposé => nom du réglage en base. */
    public const KEYS = [
        'storageUrl' => 'bunny_storage_url',
        'storageKey' => 'bunny_storage_key',
        'cdnUrl' => 'bunny_cdn_url',
        'tokenKey' => 'bunny_token_key',
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
     * Vue admin : les secrets ne sont jamais renvoyés — seule leur existence.
     *
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return [
            'storageUrl' => $this->get('storageUrl'),
            'storageKeyDefined' => $this->get('storageKey') !== '',
            'cdnUrl' => $this->get('cdnUrl'),
            'tokenKeyDefined' => $this->get('tokenKey') !== '',
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
