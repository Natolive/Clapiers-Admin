<?php

namespace App\Tests\Unit\Entity\Enum;

use App\Entity\Enum\GameVenue;
use PHPUnit\Framework\TestCase;

class GameVenueTest extends TestCase
{
    public function testLabels(): void
    {
        $this->assertSame('Domicile', GameVenue::HOME->label());
        $this->assertSame('Extérieur', GameVenue::AWAY->label());
    }
}
