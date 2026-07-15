<?php

namespace App\Application\UseCase\Team\GetMyTeam;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;

/**
 * @extends AbstractUseCase<GetMyTeamCommand>
 */
class GetMyTeamUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    /**
     * Retourne les licenciés de chaque équipe gérée par l'utilisateur,
     * groupés par équipe : [{team, members[]}]
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetMyTeamCommand) {
            throw new UseCaseException('Invalid command');
        }

        $season = $this->seasonProvider->current();
        $groups = [];

        foreach ($command->user->getTeams() as $team) {
            $groups[] = [
                'team' => $team->toArray(),
                'members' => array_map(
                    fn (Member $m) => [
                        // "Licence payée" et présence des fichiers : toutes sur
                        // la saison courante (médiathèque = source de vérité).
                        ...$m->toArray($season),
                        'hasLicenseDocument' => $this->documentRepository
                            ->findDefaultSlot($m, $season, 'license')?->hasFile() ?? false,
                        'hasProfilePicture' => $this->documentRepository
                            ->findRootDocumentSlot($m, 'identity_photo')?->hasFile() ?? false,
                    ],
                    $this->memberRepository->findByTeam($team)
                ),
            ];
        }

        return $groups;
    }
}
