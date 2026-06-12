<?php

namespace App\Tests\Unit\Entity\Enum;

use App\Entity\Enum\AppUserRole;
use PHPUnit\Framework\TestCase;

class AppUserRoleTest extends TestCase
{
    public function testEveryRoleHasALabel(): void
    {
        $this->assertSame('Super Admin', AppUserRole::SUPER_ADMIN->label());
        $this->assertSame('Admin', AppUserRole::ADMIN->label());
        $this->assertSame('Utilisateur', AppUserRole::USER->label());
        $this->assertSame('Voir messages', AppUserRole::VIEW_MESSAGE->label());
    }

    public function testInheritedRolesMatchTheSecurityHierarchy(): void
    {
        // Must stay in sync with role_hierarchy in config/packages/security.yaml
        $this->assertSame(
            [AppUserRole::SUPER_ADMIN, AppUserRole::ADMIN, AppUserRole::USER, AppUserRole::VIEW_MESSAGE],
            AppUserRole::SUPER_ADMIN->inheritedRoles(),
        );
        $this->assertSame([AppUserRole::ADMIN, AppUserRole::USER], AppUserRole::ADMIN->inheritedRoles());
        $this->assertSame([AppUserRole::VIEW_MESSAGE, AppUserRole::USER], AppUserRole::VIEW_MESSAGE->inheritedRoles());
        $this->assertSame([AppUserRole::USER], AppUserRole::USER->inheritedRoles());
    }
}
