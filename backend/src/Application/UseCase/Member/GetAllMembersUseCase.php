<?php

namespace App\Application\UseCase\Member;

use App\Common\Command\CommandInterface;
use App\Common\Service\MemberPhotoUrls;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetAllMembersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberPhotoUrls $photoUrls,
        private readonly SeasonProvider $seasonProvider,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function run(?CommandInterface $command = null): array
    {
        $members = $this->memberRepository->findAllWithTeams();
        $season = $this->seasonProvider->current();
        $photos = $this->photoUrls->forMembers($members, $season);

        return array_map(
            fn (Member $m) => [
                ...$m->toArray(),
                'profilePictureUrl' => $photos[$m->getId()] ?? null,
            ],
            $members,
        );
    }
}
