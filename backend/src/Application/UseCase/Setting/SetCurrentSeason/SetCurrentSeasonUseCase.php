<?php

namespace App\Application\UseCase\Setting\SetCurrentSeason;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;

/**
 * @extends AbstractUseCase<SetCurrentSeasonCommand>
 */
class SetCurrentSeasonUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof SetCurrentSeasonCommand) {
            throw new UseCaseException('Invalid command');
        }

        [$start, $end] = array_map('intval', explode('-', $command->season));
        if ($end !== $start + 1) {
            throw new UseCaseException('La saison doit couvrir deux années consécutives.', 422);
        }

        $this->seasonProvider->set($command->season);

        return ['season' => $command->season];
    }
}
