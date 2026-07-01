<?php

namespace App\Repository;

use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findOneByHelloAssoPaymentId(int $helloAssoPaymentId): ?Payment
    {
        return $this->findOneBy(['helloAssoPaymentId' => $helloAssoPaymentId]);
    }

    /** Le paiement en attente créé lors du checkout (le plus récent). */
    public function findWaitingByLicense(License $license): ?Payment
    {
        return $this->findOneBy(
            ['license' => $license, 'state' => PaymentState::WAITING],
            ['id' => 'DESC'],
        );
    }
}
