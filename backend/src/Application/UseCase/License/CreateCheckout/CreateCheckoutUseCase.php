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

use function Symfony\Component\String\u;

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

        if ($license->isTokenExpired()) {
            throw new UseCaseException(
                'Ce lien a expiré. Contactez le club pour en recevoir un nouveau.',
                Response::HTTP_GONE,
            );
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
            // Le payeur ne sert qu'à pré-remplir HelloAsso : un nom inutilisable est omis
            'payer' => array_filter([
                'firstName' => self::payerName($member->getFirstName()),
                'lastName' => self::payerName($member->getLastName()),
                'email' => $member->getEmail(),
            ], static fn ($v) => $v !== null),
            'metadata' => [
                'licenseId' => $license->getId(),
                'memberId' => $member->getId(),
            ],
        ];
    }

    /**
     * HelloAsso répond 400 sur un nom de payeur hors lettres latines (accents
     * compris), espace, tiret et apostrophe droite. On redresse apostrophes et
     * tirets typographiques, on translittère les alphabets non latins et on
     * retire le reste ; moins de 2 caractères → null (champ omis).
     */
    public static function payerName(string $name): ?string
    {
        $name = strtr($name, [
            '’' => "'", '‘' => "'", 'ʼ' => "'", '´' => "'", '`' => "'",
            '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-',
        ]);
        if (preg_match('/(?=\p{L})\P{Latin}/u', $name)) {
            $name = u($name)->ascii()->toString();
        }
        $name = (string) preg_replace("/[^\p{Latin}\p{M}' -]+/u", ' ', $name);
        $name = trim((string) preg_replace('/ {2,}/', ' ', $name), " '-");

        return mb_strlen($name) >= 2 ? $name : null;
    }
}
