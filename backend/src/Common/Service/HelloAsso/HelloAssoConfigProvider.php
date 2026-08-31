<?php

namespace App\Common\Service\HelloAsso;

use App\Repository\SettingRepository;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Configuration HelloAsso, entièrement en administration (table `setting`) :
 * plus aucune variable d'environnement. Point d'entrée unique — le client
 * HelloAsso ne lit rien d'autre. Une valeur absente vaut chaîne vide, sauf
 * l'URL de l'API qui retombe sur l'API de production.
 */
class HelloAssoConfigProvider
{
    /** Champ exposé => nom du réglage en base. */
    public const KEYS = [
        'baseUrl' => 'helloasso_base_url',
        'clientId' => 'helloasso_client_id',
        'clientSecret' => 'helloasso_client_secret',
        'organizationSlug' => 'helloasso_organization_slug',
        'membershipFormType' => 'helloasso_membership_form_type',
        'membershipFormSlug' => 'helloasso_membership_form_slug',
    ];

    public const DEFAULT_BASE_URL = 'https://api.helloasso.com';

    public function __construct(
        private readonly SettingRepository $settingRepository,
        // Même pool que HelloAssoClient : c'est là que vivent ses clés.
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param key-of<self::KEYS> $field */
    public function get(string $field): string
    {
        $stored = $this->settingRepository->get(self::KEYS[$field]);
        if ($stored !== null && $stored !== '') {
            return $stored;
        }

        return $field === 'baseUrl' ? self::DEFAULT_BASE_URL : '';
    }

    /**
     * Vue admin : tout sauf le secret, jamais renvoyé — seule son existence.
     *
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        $values = [];
        foreach (array_keys(self::KEYS) as $field) {
            if ($field !== 'clientSecret') {
                $values[$field] = $this->get($field);
            }
        }
        $values['clientSecretDefined'] = $this->get('clientSecret') !== '';

        return $values;
    }

    /**
     * Enregistre les champs fournis (null = inchangé) et invalide les caches du
     * client : un changement d'identifiants rend le token en cache inutilisable.
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

        $this->cache->delete(HelloAssoClient::TOKEN_CACHE_KEY);
        $this->cache->delete(HelloAssoClient::TIERS_CACHE_KEY);
    }
}
