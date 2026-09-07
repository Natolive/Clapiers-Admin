<?php

namespace App\Application\UseCase\Setting\SetBunnyConfig;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\BunnyConfigProvider;
use App\Common\UseCase\AbstractUseCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<SetBunnyConfigCommand>
 */
class SetBunnyConfigUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly BunnyConfigProvider $provider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof SetBunnyConfigCommand) {
            throw new UseCaseException('Invalid command');
        }

        $changes = array_filter([
            'storageUrl' => $command->storageUrl,
            'storageKey' => $command->storageKey,
        ], static fn (?string $value) => $value !== null && $value !== '');

        if ($changes === []) {
            throw new UseCaseException('Aucun réglage à enregistrer.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->provider->update($changes);

        return $this->provider->toArray();
    }
}
