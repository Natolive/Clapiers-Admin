<?php

namespace App\Application\UseCase\Setting\GetHelloAssoConfig;

use App\Common\Command\CommandInterface;
use App\Common\Service\HelloAsso\HelloAssoConfigProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * Configuration HelloAsso pour l'écran de réglages. Le secret client n'est
 * jamais renvoyé : seul `clientSecretDefined` dit s'il est renseigné.
 *
 * @extends AbstractUseCase<null>
 */
class GetHelloAssoConfigUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly HelloAssoConfigProvider $provider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        return $this->provider->toArray();
    }
}
