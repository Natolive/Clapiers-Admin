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

    /**
     * Les paiements encore en attente pour cette licence, le plus récent
     * d'abord. Chaque clic sur « Payer » crée un checkout : il peut y en avoir
     * plusieurs, et le plus récent n'est pas forcément celui qui a été réglé.
     *
     * @return Payment[]
     */
    public function findWaitingByLicense(License $license): array
    {
        return $this->findBy(
            ['license' => $license, 'state' => PaymentState::WAITING],
            ['id' => 'DESC'],
        );
    }
}
