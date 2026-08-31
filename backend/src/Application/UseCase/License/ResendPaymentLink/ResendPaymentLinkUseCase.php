<?php

namespace App\Application\UseCase\License\ResendPaymentLink;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\LicensePaymentLinkMailer;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\License;
use App\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renvoie le lien de paiement d'une licence validée : nouveau magic link (30 j)
 * et e-mail. C'est le seul recours quand le lien précédent a expiré — l'ancien
 * cesse aussitôt de fonctionner.
 *
 * @extends AbstractUseCase<ResendPaymentLinkCommand>
 */
class ResendPaymentLinkUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LicensePaymentLinkMailer $mailer,
    ) {
    }

    public function run(?CommandInterface $command = null): License
    {
        if (!$command instanceof ResendPaymentLinkCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->find($command->id);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        if (!in_array($license->getStatus(), [LicenseStatus::VALIDEE, LicenseStatus::EN_PAIEMENT], true)) {
            throw new UseCaseException(
                "Seule une licence validée et non réglée dispose d'un lien de paiement.",
                Response::HTTP_CONFLICT,
            );
        }

        $license->setAccessToken(bin2hex(random_bytes(32)));
        $license->extendTokenValidity();

        $this->entityManager->flush();

        $this->mailer->send($license);

        return $license;
    }
}
