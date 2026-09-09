<?php

namespace App\Application\UseCase\License\SubmitLicenseRequest;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\InscriptionsStatusProvider;
use App\Common\Service\MemberMediaSlots;
use App\Common\Service\MemberMediaStorage;
use App\Common\Service\RecaptchaVerifier;
use App\Common\Service\SeasonProvider;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\MemberStatus;
use App\Entity\InscriptionDraft;
use App\Entity\License;
use App\Entity\Member;
use App\Entity\ValueObject\Address;
use App\Entity\ValueObject\LegalRepresentative;
use App\Repository\InscriptionDraftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Création de la demande de licence publique.
 *
 * Les pièces ne sont plus envoyées après coup : elles sont déjà sur le serveur,
 * portées par le brouillon ({@see InscriptionDraft}), et ce use case les
 * rattache aux slots médiathèque du membre dans la même requête. C'est ce qui
 * garantit qu'une demande ne peut plus naître sans ses pièces — l'ancienne
 * rafale d'uploads post-validation en laissait derrière elle dès qu'un envoi
 * échouait.
 *
 * @extends AbstractUseCase<SubmitLicenseRequestCommand>
 */
class SubmitLicenseRequestUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecaptchaVerifier $recaptchaVerifier,
        private readonly SeasonProvider $seasonProvider,
        private readonly InscriptionsStatusProvider $inscriptionsStatus,
        private readonly InscriptionDraftRepository $drafts,
        private readonly MemberMediaSlots $slots,
        private readonly MemberMediaStorage $storage,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof SubmitLicenseRequestCommand) {
            throw new UseCaseException('Invalid command');
        }

        if (!$this->inscriptionsStatus->isFormOpen()) {
            throw new UseCaseException(
                'Les inscriptions en ligne sont fermées pour le moment.',
                Response::HTTP_FORBIDDEN,
            );
        }

        // Résolu avant toute création : un token de brouillon inconnu ne doit
        // pas laisser derrière lui un membre et une licence orphelins.
        $draft = $this->resolveDraft($command->draftToken);

        // Le captcha a déjà été vérifié à l'ouverture du brouillon, et son
        // token — secret, unique — en tient lieu ici. Sans brouillon (appel
        // direct), il reste exigé.
        if ($draft === null && !$this->recaptchaVerifier->verify((string) $command->recaptchaToken)) {
            throw new UseCaseException('Veuillez valider le captcha.');
        }

        $member = new Member();
        $member->setFirstName($command->firstName);
        $member->setLastName($command->lastName);
        $member->setPhoneNumber($command->phoneNumber);
        $member->setEmail($command->email);
        $member->setAddress(new Address($command->addressStreet, $command->addressZip, $command->addressCity));
        $member->setGender($command->gender);
        $member->setBirthDate($this->parseBirthDate($command->birthDate));
        $member->setNationality($command->nationality);
        $member->setLicenseNumber($command->licenseNumber);
        $member->setStatus(MemberStatus::PENDING_VALIDATION);
        $member->setLegalRepresentative(new LegalRepresentative(
            $command->legalRepFirstName ?? '',
            $command->legalRepLastName ?? '',
            $command->legalRepEmail ?? '',
            $command->legalRepPhone ?? '',
        ));

        $license = new License();
        $license->setMember($member);
        $license->setSeason($this->seasonProvider->current());
        $license->setStatus(LicenseStatus::SOUMISE);
        $license->setLicenseNumber($command->licenseNumber);
        $license->setHealthDeclaration($command->healthDeclaration);
        $license->setAccessToken(bin2hex(random_bytes(32)));

        $this->entityManager->persist($member);
        $this->entityManager->persist($license);
        $this->entityManager->flush();

        if ($draft !== null) {
            $this->attachDraftDocuments($draft, $member, $license);
        }

        return $license;
    }

    private function resolveDraft(?string $token): ?InscriptionDraft
    {
        if ($token === null || $token === '') {
            return null;
        }

        $draft = $this->drafts->findOneByToken($token);
        if (!$draft) {
            throw new UseCaseException('Brouillon introuvable', Response::HTTP_NOT_FOUND);
        }

        return $draft;
    }

    /**
     * Déplace les pièces du brouillon vers la médiathèque du membre, puis
     * supprime le brouillon.
     *
     * Copie puis suppression, jamais l'inverse : `copyTo()` laisse l'original en
     * place, et les objets du brouillon ne partent qu'une fois les nouveaux noms
     * commités — sinon un échec en cours de route laisserait la base pointer sur
     * des fichiers déjà effacés.
     */
    private function attachDraftDocuments(InscriptionDraft $draft, Member $member, License $license): void
    {
        $sources = [];
        foreach ($draft->getDocuments() as $systemKey => $meta) {
            $slot = $this->slots->resolve($member, $license->getSeason(), $systemKey);
            $storedName = $this->storage->copyTo($meta['storedName'], (int) $member->getId());
            $slot->setFile($storedName, $meta['originalName'], $meta['mimeType'] ?? null, $meta['size'] ?? null);

            // Marqueur sur la licence pour le badge « certificat déposé » côté admin.
            if ($systemKey === 'medical_certificate') {
                $license->setMedicalCertificateFileName($storedName);
            }

            $sources[] = $meta['storedName'];
        }

        $this->entityManager->remove($draft);
        $this->entityManager->flush();

        foreach ($sources as $source) {
            $this->storage->delete($source);
        }
    }

    private function parseBirthDate(string $birthDate): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($birthDate);
        } catch (\Exception) {
            throw new UseCaseException('Date de naissance invalide.', 422);
        }
    }
}
