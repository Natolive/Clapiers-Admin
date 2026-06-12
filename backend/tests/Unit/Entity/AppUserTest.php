<?php

namespace App\Tests\Unit\Entity;

use App\Entity\AppUser;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

class AppUserTest extends TestCase
{
    public function testTwoUnpersistedTeamsAreNotConsideredEqual(): void
    {
        $teamA = (new Team())->setName('A');
        $teamB = (new Team())->setName('B');

        $user = new AppUser();
        $user->addTeam($teamA);
        $user->addTeam($teamB);

        // Les deux ids sont null : sans comparaison d'identité, addTeam
        // écarterait silencieusement la seconde équipe
        $this->assertCount(2, $user->getTeams());
        $this->assertTrue($user->hasTeam($teamA));
        $this->assertFalse($user->hasTeam((new Team())->setName('C')));
    }

    public function testSerializationMasksThePasswordHash(): void
    {
        $user = new AppUser();
        $user->setEmail('coach@test.fr');
        $user->setPassword('$2y$04$realbcryphashvalue');

        $data = $user->__serialize();
        $passwordKey = "\0".AppUser::class."\0password";

        $this->assertSame(hash('crc32c', '$2y$04$realbcryphashvalue'), $data[$passwordKey]);
        $this->assertNotSame('$2y$04$realbcryphashvalue', $data[$passwordKey]);
    }

    public function testEraseCredentialsIsADeprecatedNoOp(): void
    {
        $user = new AppUser();
        $user->setPassword('hash');

        $this->expectUserDeprecationMessageMatches('/eraseCredentials/');
        $user->eraseCredentials();

        $this->assertSame('hash', $user->getPassword());
    }
}
