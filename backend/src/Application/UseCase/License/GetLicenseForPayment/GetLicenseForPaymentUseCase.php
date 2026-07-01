<?php

namespace App\Application\UseCase\License\GetLicenseForPayment;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\UseCase\AbstractUseCase;
use App\Repository\LicenseRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vue publique du portail de paiement (par magic link). Renvoie le minimum
 * nécessaire à l'affichage : pas d'adresse ni de données superflues.
 *
 * @extends AbstractUseCase<GetLicenseForPaymentCommand>
 */
class GetLicenseForPaymentUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LicenseRepository $licenseRepository,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof GetLicenseForPaymentCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->findOneByAccessToken($command->token);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        $member = $license->getMember();

        return [
            'status' => $license->getStatus()->value,
            'season' => $license->getSeason(),
            'amount' => $license->getAmount(),
            'firstName' => $member->getFirstName(),
            'lastName' => $member->getLastName(),
        ];
    }
}
