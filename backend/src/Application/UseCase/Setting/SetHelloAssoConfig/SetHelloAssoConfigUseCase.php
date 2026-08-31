<?php

namespace App\Application\UseCase\Setting\SetHelloAssoConfig;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\HelloAsso\HelloAssoConfigProvider;
use App\Common\UseCase\AbstractUseCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<SetHelloAssoConfigCommand>
 */
class SetHelloAssoConfigUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly HelloAssoConfigProvider $provider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof SetHelloAssoConfigCommand) {
            throw new UseCaseException('Invalid command');
        }

        $changes = array_filter([
            'baseUrl' => $command->baseUrl,
            'clientId' => $command->clientId,
            'clientSecret' => $command->clientSecret,
            'organizationSlug' => $command->organizationSlug,
            'membershipFormType' => $command->membershipFormType,
            'membershipFormSlug' => $command->membershipFormSlug,
        ], static fn (?string $value) => $value !== null && $value !== '');

        if ($changes === []) {
            throw new UseCaseException('Aucun réglage à enregistrer.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->provider->update($changes);

        return $this->provider->toArray();
    }
}
