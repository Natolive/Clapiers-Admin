<?php

namespace App\Repository;

use App\Entity\AppUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\App;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<AppUser>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppUser::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof AppUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array{data: AppUser[], total: int}
     */
    public function findPaginated(
        int $page,
        int $limit,
        string $sortField,
        string $sortOrder,
        ?string $search = null,
    ): array {
        $allowedFields = [
            'email' => 'u.email',
            'createdAt' => 'u.createdAt',
            'updatedAt' => 'u.updatedAt',
            'member.name' => 'm.firstName',
        ];

        $orderColumn = $allowedFields[$sortField] ?? 'u.email';
        $orderDir = strtolower($sortOrder) === 'desc' ? 'DESC' : 'ASC';
        $isMemberNameSort = $sortField === 'member.name';

        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.member', 'm')
            ->addSelect('m');

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $qb->andWhere('LOWER(u.email) LIKE LOWER(:search) OR LOWER(m.firstName) LIKE LOWER(:search) OR LOWER(m.lastName) LIKE LOWER(:search) OR LOWER(CONCAT(m.firstName, \' \', m.lastName)) LIKE LOWER(:search)')
                ->setParameter('search', $searchTerm);
        }

        $total = (clone $qb)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb->orderBy($orderColumn, $orderDir);

        if ($isMemberNameSort) {
            $qb->addOrderBy('m.lastName', $orderDir);
        }

        if ($orderColumn !== 'u.email') {
            $qb->addOrderBy('u.email', 'ASC');
        }

        $data = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return ['data' => $data, 'total' => (int) $total];
    }
}
