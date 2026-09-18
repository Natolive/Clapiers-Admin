<?php

namespace App\Application\UseCase\Setting\GetBunnyConfig;

use App\Common\Command\CommandInterface;
use App\Common\Service\BunnyConfigProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * Configuration Bunny (Storage + CDN) pour l'écran de réglages. Les secrets ne
 * sont jamais renvoyés : seuls `storageKeyDefined` et `tokenKeyDefined` disent
 * s'ils sont renseignés.
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
