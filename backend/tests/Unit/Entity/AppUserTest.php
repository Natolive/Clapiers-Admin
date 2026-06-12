<?php

namespace App\Tests\Unit\Entity;

use App\Entity\AppUser;
use PHPUnit\Framework\TestCase;

class AppUserTest extends TestCase
{
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
