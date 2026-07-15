<?php

namespace App\Application\UseCase\Member\Media\GetMemberMedia;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\MemberMediaSeeder;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<GetMemberMediaCommand>
 */
class GetMemberMediaUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaSeeder $seeder,
        private readonly SeasonProvider $seasonProvider,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return \App\Entity\MemberDocument[]
     */
    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetMemberMediaCommand) {
            throw new UseCaseException('Invalid command');
        }

        $member = $this->memberRepository->find($command->memberId);
        if (!$member) {
            throw new UseCaseException('Member not found', Response::HTTP_NOT_FOUND);
        }

        // Garantit à la demande le mapping par défaut : dossiers racine (Identité…)
        // indépendants de la saison + dossier de la saison courante et ses slots.
        $this->seeder->ensureRootFolders($member);
        $this->seeder->ensureSeason($member, $this->seasonProvider->current());
        $this->entityManager->flush();

        return $this->documentRepository->findRootsByMember($member);
    }
}
