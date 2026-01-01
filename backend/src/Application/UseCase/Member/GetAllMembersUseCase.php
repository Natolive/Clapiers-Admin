<?php

namespace App\Application\UseCase\Member;

use App\Common\Command\CommandInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\MemberRepository;

/**
 * @extends AbstractUseCase<null>
 */
class GetAllMembersUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository
    ) {
    }

    /**
     * @return array<int, Member>
     */
    public function run(?CommandInterface $command = null): array
    {
        return $this->memberRepository->findAll();
    }
}
