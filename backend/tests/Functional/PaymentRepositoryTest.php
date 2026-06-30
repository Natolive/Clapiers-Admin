<?php

namespace App\Tests\Functional;

use App\Entity\Enum\PaymentState;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Tests\Support\ApiTestCase;

/**
 * La recherche par identifiant de paiement HelloAsso sert de clé d'idempotence
 * au webhook (Lot 4). On la valide ici en persistant un Payment réel.
 */
class PaymentRepositoryTest extends ApiTestCase
{
    public function testFindOneByHelloAssoPaymentId(): void
    {
        $license = $this->aLicense()->persist();

        $payment = (new Payment())
            ->setLicense($license)
            ->setAmount(12000)
            ->setState(PaymentState::AUTHORIZED)
            ->setHelloAssoPaymentId(987654);

        $this->em()->persist($payment);
        $this->em()->flush();

        /** @var PaymentRepository $repository */
        $repository = $this->em()->getRepository(Payment::class);

        $this->assertSame($payment->getId(), $repository->findOneByHelloAssoPaymentId(987654)?->getId());
        $this->assertNull($repository->findOneByHelloAssoPaymentId(111111));
    }
}
