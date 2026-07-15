<?php

namespace App\Application\UseCase\License\GetLicenseReview;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Member;
use App\Repository\LicenseRepository;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Détail d'une demande de licence pour la revue admin : toutes les infos de la
 * demande (licence + membre) et l'état des pièces déposées, avec de quoi les
 * télécharger via la médiathèque du membre (memberId + nodeId).
 *
 * @extends AbstractUseCase<GetLicenseReviewCommand>
 */
class GetLicenseReviewUseCase extends AbstractUseCase
{
    /**
     * Pièces attendues à l'inscription. `scope` = où le slot est rangé dans la
     * médiathèque : « root » (dossier hors saison, ex. Identité) ou « season »
     * (dossier de la saison de la licence).
     */
    private const DOCUMENTS = [
        ['key' => 'identity_photo', 'label' => "Photo d'identité", 'scope' => 'root'],
        ['key' => 'id_card', 'label' => "Pièce d'identité", 'scope' => 'root'],
        ['key' => 'medical_certificate', 'label' => 'Certificat médical', 'scope' => 'season'],
        ['key' => 'attestation', 'label' => 'Attestation santé', 'scope' => 'season'],
    ];

    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberRepository $memberRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetLicenseReviewCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->find($command->id);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        $member = $license->getMember();

        return [
            'license' => $license->toArray(),
            'memberId' => $member->getId(),
            'documents' => array_map(
                fn (array $slot) => $this->describeDocument($member, $license->getSeason(), $slot),
                self::DOCUMENTS,
            ),
            'existingMember' => $this->describeExistingMember($member, $license->getSeason()),
        ];
    }

    /**
     * Autre membre partageant l'email de la demande (réinscription probable) :
     * l'admin choisira, à l'approbation, de fusionner ou de créer un nouveau.
     *
     * @return array<string, mixed>|null
     */
    private function describeExistingMember(Member $member, string $season): ?array
    {
        $existing = $this->memberRepository->findOneByEmailExcluding($member->getEmail(), (int) $member->getId());
        if ($existing === null) {
            return null;
        }

        return [
            'id' => $existing->getId(),
            'firstName' => $existing->getFirstName(),
            'lastName' => $existing->getLastName(),
            'email' => $existing->getEmail(),
            'status' => $existing->getStatus()->value,
            'hasLicenseThisSeason' => $this->licenseRepository->findOneByMemberAndSeason($existing, $season) !== null,
        ];
    }

    /**
     * @param array{key: string, label: string, scope: string} $slot
     *
     * @return array<string, mixed>
     */
    private function describeDocument(Member $member, string $season, array $slot): array
    {
        $document = $slot['scope'] === 'root'
            ? $this->documentRepository->findRootDocumentSlot($member, $slot['key'])
            : $this->documentRepository->findDefaultSlot($member, $season, $slot['key']);

        $uploaded = $document !== null && $document->hasFile();

        return [
            'key' => $slot['key'],
            'label' => $slot['label'],
            'uploaded' => $uploaded,
            'nodeId' => $uploaded ? $document->getUuid() : null,
            'originalName' => $uploaded ? $document->getOriginalName() : null,
            'mimeType' => $uploaded ? $document->getMimeType() : null,
            'size' => $uploaded ? $document->getSize() : null,
        ];
    }
}
