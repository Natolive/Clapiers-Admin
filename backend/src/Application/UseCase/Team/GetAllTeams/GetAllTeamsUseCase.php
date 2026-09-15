<?php

namespace App\Application\UseCase\Team\GetAllTeams;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Team;
use App\Repository\MemberRepository;
use App\Repository\TeamRepository;

/**
 * Liste des équipes enrichie des compteurs d'effectif de la saison demandée :
 * la page d'administration doit pouvoir arbitrer (équipe vide, licences non
 * payées) sans déplier chaque équipe.
 *
 * @extends AbstractUseCase<GetAllTeamsCommand>
 */
class GetAllTeamsUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly MemberRepository $memberRepository,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetAllTeamsCommand) {
            throw new UseCaseException('Invalid command');
        }

        $season = $command->season ?? $this->seasonProvider->current();
        $counts = $this->memberRepository->countActiveByTeam($season);

        return array_map(
            fn (Team $team) => [
                ...$team->toArray(),
                'season' => $season,
                'memberCount' => $counts[$team->getId()]['members'] ?? 0,
                'paidCount' => $counts[$team->getId()]['paid'] ?? 0,
            ],
            $this->teamRepository->findBy([], ['name' => 'ASC']),
        );
    }
}
