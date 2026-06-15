<?php

namespace App\Tests\Unit\Entity\Enum;

use App\Entity\Enum\GameHistoryAction;
use PHPUnit\Framework\TestCase;

class GameHistoryActionTest extends TestCase
{
    public function testLabels(): void
    {
        $this->assertSame('Création', GameHistoryAction::CREATED->label());
        $this->assertSame('Modification', GameHistoryAction::UPDATED->label());
        $this->assertSame('Suppression', GameHistoryAction::DELETED->label());
    }
}
