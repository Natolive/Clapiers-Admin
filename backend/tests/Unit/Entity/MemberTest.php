<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Member;
use PHPUnit\Framework\TestCase;

class MemberTest extends TestCase
{
    public function testNewMemberGetsARandomHexColor(): void
    {
        $member = new Member();

        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $member->getColor());
    }

    public function testColorCanBeOverridden(): void
    {
        $member = new Member();
        $member->setColor('#123ABC');

        $this->assertSame('#123ABC', $member->getColor());
    }
}
