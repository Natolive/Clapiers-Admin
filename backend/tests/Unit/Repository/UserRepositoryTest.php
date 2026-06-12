<?php

namespace App\Tests\Unit\Repository;

use App\Entity\AppUser;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class UserRepositoryTest extends TestCase
{
    public function testUpgradePasswordRefusesForeignUserClasses(): void
    {
        $foreignUser = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string
            {
                return 'hash';
            }
        };

        $this->expectException(UnsupportedUserException::class);

        $this->makeRepository()->upgradePassword($foreignUser, 'new-hash');
    }

    private function makeRepository(): UserRepository
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn(new ClassMetadata(AppUser::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new UserRepository($registry);
    }
}
