<?php

namespace App\Application\UseCase\License\ApproveLicense;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
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
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @extends AbstractUseCase<ApproveLicenseCommand>
 */
class ApproveLicenseUseCase extends AbstractUseCase
{
    private const TOKEN_VALIDITY = 'P30D';

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
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'CONTACT_SENDER_EMAIL')]
        private readonly string $senderEmail,
        #[Autowire(env: 'APP_FRONTEND_URL')]
        private readonly string $frontendUrl,
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
        if ($command->replaceMemberId !== null) {
            $this->mergeIntoExistingMember($license, $command->replaceMemberId);
        }

        $license->setHelloAssoTierId($command->helloAssoTierId);
        $license->setAmount($command->amount);
        $license->setStatus(LicenseStatus::VALIDEE);
        $license->setApprovedAt(new \DateTimeImmutable('now'));

        if ($license->getAccessToken() === null) {
            $license->setAccessToken(bin2hex(random_bytes(32)));
        }
        $license->setTokenExpiresAt((new \DateTimeImmutable('now'))->add(new \DateInterval(self::TOKEN_VALIDITY)));

        $license->getMember()->setStatus(MemberStatus::ACTIVE);

        $this->entityManager->flush();

        $this->sendPaymentLinkEmail($license);

        return $license;
    }

    /**
     * Rattache la licence à un membre existant (même email), déplace les pièces
     * déposées dans sa médiathèque, met à jour ses coordonnées, puis supprime la
     * fiche en double créée par la demande.
     */
    private function mergeIntoExistingMember(License $license, int $existingMemberId): void
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

        $this->moveDocuments($source, $existing, $license->getSeason());

        $license->setMember($existing);
        $this->copyCoordinates($source, $existing);

        // La fiche en double n'a plus de licence rattachée : ses lignes
        // médiathèque partent en cascade (base), les fichiers déplacés restant
        // référencés par le membre existant.
        $this->entityManager->remove($source);
    }

    private function moveDocuments(Member $source, Member $target, string $season): void
    {
        foreach (self::ROOT_KEYS as $key) {
            $src = $this->documentRepository->findRootDocumentSlot($source, $key);
            $dest = $this->documentRepository->findRootDocumentSlot($target, $key);
            $this->moveFile($src, $dest);
        }

        foreach (self::SEASON_KEYS as $key) {
            $src = $this->documentRepository->findDefaultSlot($source, $season, $key);
            $dest = $this->documentRepository->findDefaultSlot($target, $season, $key);
            $this->moveFile($src, $dest);
        }
    }

    private function moveFile(?MemberDocument $src, ?MemberDocument $dest): void
    {
        if ($src === null || $dest === null || !$src->hasFile()) {
            return;
        }

        // Remplace la pièce éventuellement déjà présente côté membre existant.
        if ($dest->hasFile()) {
            $this->storage->delete($dest->getStoredName());
        }

        $dest->setFile(
            (string) $src->getStoredName(),
            $src->getOriginalName() ?? $dest->getName(),
            $src->getMimeType(),
            $src->getSize(),
        );
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

    private function sendPaymentLinkEmail(License $license): void
    {
        $member = $license->getMember();
        $paymentUrl = rtrim($this->frontendUrl, '/').'/licence/'.$license->getAccessToken();
        $amountEuros = number_format(($license->getAmount() ?? 0) / 100, 2, ',', ' ');

        $email = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, 'Clapiers Volley-Ball'))
            ->to(new Address($member->getEmail(), trim($member->getFirstName().' '.$member->getLastName())))
            ->subject('Votre licence est validée — réglez votre adhésion')
            ->htmlTemplate('emails/license_approved.html.twig')
            ->context([
                'firstName' => $member->getFirstName(),
                'season' => $license->getSeason(),
                'amount' => $amountEuros,
                'paymentUrl' => $paymentUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send license approval email', ['exception' => $e, 'licenseId' => $license->getId()]);
        }
    }
}
