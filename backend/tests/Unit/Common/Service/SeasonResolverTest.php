<?php

namespace App\Tests\Unit\Common\Service;

use App\Common\Service\SeasonResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SeasonResolverTest extends TestCase
{
    #[DataProvider('dateProvider')]
    public function testForDateComputesTheSportingSeason(string $date, string $expected): void
    {
        $this->assertSame($expected, (new SeasonResolver())->forDate(new \DateTimeImmutable($date)));
    }

    public static function dateProvider(): iterable
    {
        yield 'before September stays in previous season' => ['2026-06-30', '2025-2026'];
        yield 'August 31 is still previous season' => ['2026-08-31', '2025-2026'];
        yield 'September 1 starts the new season' => ['2026-09-01', '2026-2027'];
        yield 'December is the new season' => ['2026-12-15', '2026-2027'];
    }

    public function testCurrentReturnsAWellFormedSeason(): void
    {
        $this->assertMatchesRegularExpression('/^\d{4}-\d{4}$/', (new SeasonResolver())->current());
    }
}
