<?php

namespace App\Tests\Unit\Entity\Enum;

use App\Entity\Enum\LicenseStatus;
use PHPUnit\Framework\TestCase;

class LicenseStatusTest extends TestCase
{
    public function testActiveMembershipIsTheCountedTriplet(): void
    {
        $this->assertSame(
            [LicenseStatus::VALIDEE, LicenseStatus::EN_PAIEMENT, LicenseStatus::PAYEE],
            LicenseStatus::activeMembership(),
        );
    }

    public function testActiveMembershipValuesMatchTheEnumBackingValues(): void
    {
        $this->assertSame(
            ['validee', 'en_paiement', 'payee'],
            LicenseStatus::activeMembershipValues(),
        );
    }
}
