<?php

namespace App\Application\UseCase\Setting\SetInscriptionsStatus;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\InscriptionsStatusProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * @extends AbstractUseCase<SetInscriptionsStatusCommand>
 */
class SetInscriptionsStatusUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly InscriptionsStatusProvider $provider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof SetInscriptionsStatusCommand) {
            throw new UseCaseException('Invalid command');
        }

        $this->provider->setOpen($command->open);

        return ['open' => $command->open];
    }
}
