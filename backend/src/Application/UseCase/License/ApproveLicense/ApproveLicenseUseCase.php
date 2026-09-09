<?php

namespace App\Application\UseCase\License\ApproveLicense;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\LicensePaymentLinkMailer;
use App\Common\Service\MemberMediaSeeder;
use App\Common\Service\MemberMediaStorage;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Repository\LicenseRepository;
use App\Repository\MemberDocumentRepository;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractUseCase<ApproveLicenseCommand>
 */
class ApproveLicenseUseCase extends AbstractUseCase
{
    /** Pièces déplaçables lors d'une fusion (par portée médiathèque). */
    private const ROOT_KEYS = ['identity_photo', 'id_card'];
    private const SEASON_KEYS = ['medical_certificate', 'attestation'];

    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly MemberRepository $memberRepository,
        private readonly MemberDocumentRepository $documentRepository,
        private readonly MemberMediaSeeder $seeder,
        private readonly MemberMediaStorage $storage,
        private readonly EntityManagerInterface $entityManager,
        private readonly LicensePaymentLinkMailer $mailer,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof ApproveLicenseCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->find($command->id);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        if ($license->getStatus() !== LicenseStatus::SOUMISE) {
            throw new UseCaseException('Cette demande a déjà été traitée.', Response::HTTP_CONFLICT);
        }

        // Réinscription : fusionner dans le membre existant choisi par l'admin.
        // Les fichiers devenus inutiles ne sont supprimés qu'après le commit.
        $obsoleteFiles = [];
        if ($command->replaceMemberId !== null) {
            $obsoleteFiles = $this->mergeIntoExistingMember($license, $command->replaceMemberId);
        }

        $license->setHelloAssoTierId($command->helloAssoTierId);
        $license->setAmount($command->amount);
        $license->setStatus(LicenseStatus::VALIDEE);
        $license->setApprovedAt(new \DateTimeImmutable('now'));

        if ($license->getAccessToken() === null) {
            $license->setAccessToken(bin2hex(random_bytes(32)));
        }
        $license->extendTokenValidity();

        $license->getMember()->setStatus(MemberStatus::ACTIVE);

        $this->entityManager->flush();

        // Après le commit seulement : supprimer plus tôt, c'est perdre les
        // fichiers si la suite échoue — la base pointerait sur des objets déjà
        // effacés de la zone.
        foreach ($obsoleteFiles as $obsoleteFile) {
            $this->storage->deleteQuietly($obsoleteFile);
        }

        $this->mailer->send($license);

        return $license;
    }

    /**
     * Rattache la licence à un membre existant (même email), déplace les pièces
     * déposées dans sa médiathèque, met à jour ses coordonnées, puis supprime la
     * fiche en double créée par la demande.
     *
     * @return list<string> fichiers rendus obsolètes, à supprimer après le flush
     */
    private function mergeIntoExistingMember(License $license, int $existingMemberId): array
    {
        $existing = $this->memberRepository->find($existingMemberId);
        if (!$existing) {
            throw new UseCaseException('Membre existant introuvable.', Response::HTTP_NOT_FOUND);
        }

        $source = $license->getMember();

        if (mb_strtolower($existing->getEmail()) !== mb_strtolower($source->getEmail())) {
            throw new UseCaseException(
                "Le membre sélectionné ne correspond pas à l'email de la demande.",
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->licenseRepository->findOneByMemberAndSeason($existing, $license->getSeason()) !== null) {
            throw new UseCaseException(
                sprintf('Ce membre a déjà une licence pour la saison %s.', $license->getSeason()),
                Response::HTTP_CONFLICT,
            );
        }

        // Prépare les slots cibles avant de déplacer les fichiers.
        $this->seeder->ensureRootFolders($existing);
        $this->seeder->ensureSeason($existing, $license->getSeason());
        $this->entityManager->flush();

        $obsoleteFiles = $this->moveDocuments($source, $existing, $license->getSeason());

        $license->setMember($existing);
        $this->copyCoordinates($source, $existing);

        // La fiche en double n'a plus de licence rattachée : ses lignes
        // médiathèque partent en cascade (base), les fichiers déplacés restant
        // référencés par le membre existant.
        $this->entityManager->remove($source);

        return $obsoleteFiles;
    }

    /** @return list<string> */
    private function moveDocuments(Member $source, Member $target, string $season): array
    {
        $obsoleteFiles = [];

        foreach (self::ROOT_KEYS as $key) {
            $src = $this->documentRepository->findRootDocumentSlot($source, $key);
            $dest = $this->documentRepository->findRootDocumentSlot($target, $key);
            $obsoleteFiles = [...$obsoleteFiles, ...$this->moveFile($src, $dest)];
        }

        foreach (self::SEASON_KEYS as $key) {
            $src = $this->documentRepository->findDefaultSlot($source, $season, $key);
            $dest = $this->documentRepository->findDefaultSlot($target, $season, $key);
            $obsoleteFiles = [...$obsoleteFiles, ...$this->moveFile($src, $dest)];
        }

        return $obsoleteFiles;
    }

    /** @return list<string> */
    private function moveFile(?MemberDocument $src, ?MemberDocument $dest): array
    {
        if ($src === null || $dest === null || !$src->hasFile()) {
            return [];
        }

        $obsoleteFiles = [];

        // La pièce éventuellement déjà présente côté membre existant est
        // remplacée : son fichier ne sert plus, mais on ne l'efface qu'après.
        if ($dest->hasFile()) {
            $obsoleteFiles[] = (string) $dest->getStoredName();
        }

        // Le stockage est rangé par membre : la pièce doit suivre dans le
        // dossier du membre existant, sinon elle resterait sous l'id de la
        // fiche en double, qui est supprimée juste après.
        $storedName = $this->storage->copyTo(
            (string) $src->getStoredName(),
            (int) $dest->getMember()->getId(),
        );

        if ($storedName !== $src->getStoredName()) {
            $obsoleteFiles[] = (string) $src->getStoredName();
        }

        $dest->setFile(
            $storedName,
            $src->getOriginalName() ?? $dest->getName(),
            $src->getMimeType(),
            $src->getSize(),
        );

        return $obsoleteFiles;
    }

    private function copyCoordinates(Member $source, Member $target): void
    {
        $target->setFirstName($source->getFirstName());
        $target->setLastName($source->getLastName());
        $target->setEmail($source->getEmail());
        $target->setPhoneNumber($source->getPhoneNumber());
        $target->setAddress($source->getAddress());
        $target->setGender($source->getGender());
        $target->setBirthDate($source->getBirthDate());
        $target->setNationality($source->getNationality());
        $target->setLicenseNumber($source->getLicenseNumber());
        $target->setLegalRepresentative($source->getLegalRepresentative());
    }
}
