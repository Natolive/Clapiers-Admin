<?php

namespace App\Application\UseCase\Setting\GetBunnyConfig;

use App\Common\Command\CommandInterface;
use App\Common\Service\BunnyConfigProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * Configuration Bunny Storage pour l'écran de réglages. La clé d'accès n'est
 * jamais renvoyée : seul `storageKeyDefined` dit si elle est renseignée.
 *
 * @extends AbstractUseCase<null>
 */
class GetBunnyConfigUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly BunnyConfigProvider $provider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return $this->provider->toArray();
    }
}
