<?php

namespace App\Tests\Unit\Common\Service;

use App\Common\Service\SeasonProvider;
use App\Common\Service\SeasonResolver;
use App\Repository\SettingRepository;
use PHPUnit\Framework\TestCase;

class SeasonProviderTest extends TestCase
{
    public function testUsesConfiguredSeasonWhenSet(): void
    {
        $settings = $this->createStub(SettingRepository::class);
        $settings->method('get')->willReturn('2030-2031');

        $provider = new SeasonProvider($settings, new SeasonResolver());

        $this->assertSame('2030-2031', $provider->current());
    }

    public function testFallsBackToComputedWhenUnset(): void
    {
        // Stub non configuré : get() renvoie null → repli sur le calcul par date.
        $provider = new SeasonProvider($this->createStub(SettingRepository::class), new SeasonResolver());

        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $provider->current());
        $this->assertSame($provider->computed(), $provider->current());
    }
}
