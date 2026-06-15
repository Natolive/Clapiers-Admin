<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\AppUser;
use App\Entity\Game;
use App\Entity\Member;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Charge réellement les fixtures de dev : garantit qu'elles suivent les
 * évolutions des entités (ex: Member::setTeam() supprimé par le passage au
 * ManyToMany avait cassé doctrine:fixtures:load sans que rien ne le détecte).
 * La transaction DAMA annule tout à la fin.
 */
class FixturesSmokeTest extends KernelTestCase
{
    public function testFixturesLoadAgainstTheCurrentSchema(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $container->get(AppFixtures::class)->load($entityManager);

        $this->assertGreaterThan(0, $entityManager->getRepository(Team::class)->count([]));
        $this->assertGreaterThan(0, $entityManager->getRepository(Member::class)->count([]));
        $this->assertGreaterThan(0, $entityManager->getRepository(Game::class)->count([]));
        $this->assertGreaterThan(0, $entityManager->getRepository(AppUser::class)->count([]));

        // Every fixture member must belong to at least one team (ManyToMany)
        $member = $entityManager->getRepository(Member::class)->findOneBy([]);
        $this->assertGreaterThan(0, $member->getTeams()->count());
    }
}
