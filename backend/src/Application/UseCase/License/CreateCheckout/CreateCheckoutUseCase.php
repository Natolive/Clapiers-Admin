<?php

namespace App\Application\UseCase\License\CreateCheckout;

use App\Common\Command\CommandInterface;
use App\Common\Exception\UseCaseException;
use App\Common\Service\HelloAsso\HelloAssoClientInterface;
use App\Common\UseCase\AbstractUseCase;
use App\Entity\Enum\LicenseStatus;
use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Payment;
use App\Repository\LicenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Crée un checkout-intent HelloAsso pour une licence validée et renvoie l'URL
 * de redirection vers la page de paiement. Le paiement est tracé en PENDING
 * (WAITING) ; le webhook (Lot 4) fait foi pour la confirmation.
 *
 * @extends AbstractUseCase<CreateCheckoutCommand>
 */
class CreateCheckoutUseCase extends AbstractUseCase
{
    public function __construct(
        private readonly LicenseRepository $licenseRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HelloAssoClientInterface $helloAssoClient,
        #[Autowire(env: 'APP_FRONTEND_URL')]
        private readonly string $frontendUrl,
    ) {
    }

    public function run(?CommandInterface $command = null): array
    {
        if (!$command instanceof CreateCheckoutCommand) {
            throw new UseCaseException('Invalid command');
        }

        $license = $this->licenseRepository->findOneByAccessToken($command->token);
        if (!$license) {
            throw new UseCaseException('Licence introuvable', Response::HTTP_NOT_FOUND);
        }

        if (!in_array($license->getStatus(), [LicenseStatus::VALIDEE, LicenseStatus::EN_PAIEMENT], true)) {
            throw new UseCaseException("Cette licence n'est pas disponible au paiement.", Response::HTTP_CONFLICT);
        }

        $amount = $license->getAmount();
        if ($amount === null || $amount <= 0) {
            throw new UseCaseException('Montant de licence non défini.', Response::HTTP_CONFLICT);
        }

        $response = $this->helloAssoClient->createCheckoutIntent($this->buildCheckoutBody($license, $amount));

        $payment = (new Payment())
            ->setLicense($license)
            ->setAmount($amount)
            ->setState(PaymentState::WAITING)
            ->setHelloAssoCheckoutIntentId(isset($response['id']) ? (int) $response['id'] : null);

        $license->setStatus(LicenseStatus::EN_PAIEMENT);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return ['redirectUrl' => $response['redirectUrl'] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCheckoutBody(License $license, int $amount): array
    {
        $member = $license->getMember();
        $base = rtrim($this->frontendUrl, '/').'/licence/'.$license->getAccessToken();

        return [
            'totalAmount' => $amount,
            'initialAmount' => $amount,
            'itemName' => sprintf('Licence %s — %s %s', $license->getSeason(), $member->getFirstName(), $member->getLastName()),
            'backUrl' => $base.'?status=back',
            'errorUrl' => $base.'?status=error',
            'returnUrl' => $base.'?status=success',
            'containsDonation' => false,
            'payer' => [
                'firstName' => $member->getFirstName(),
                'lastName' => $member->getLastName(),
                'email' => $member->getEmail(),
            ],
            'metadata' => [
                'licenseId' => $license->getId(),
                'memberId' => $member->getId(),
            ],
        ];
    }
}
