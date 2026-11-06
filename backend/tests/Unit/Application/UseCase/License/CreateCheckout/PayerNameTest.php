<?php

namespace App\Tests\Unit\Application\UseCase\License\CreateCheckout;

use App\Application\UseCase\License\CreateCheckout\CreateCheckoutUseCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayerNameTest extends TestCase
{
    public static function names(): iterable
    {
        yield 'accents gardés' => ['Hélène-Zoé', 'Hélène-Zoé'];
        yield 'apostrophe typo' => ['Keali’i', "Keali'i"];
        yield 'apostrophe ouvrante / modificateur' => ['O‘Neil ʼA', "O'Neil 'A"];
        yield 'tirets longs' => ['Jean–Marc—Paul', 'Jean-Marc-Paul'];
        yield 'chiffres, ponctuation, emoji retirés' => ['Jean2 (Junior). 🏐', 'Jean Junior'];
        yield 'espaces multiples / insécables' => ["  Anne \u{00A0}  Sophie ", 'Anne Sophie'];
        yield 'alphabet non latin translittéré' => ['Дмитрий', 'Dmitrij'];
        yield 'bords nettoyés' => ["-'Léa'-", 'Léa'];
        yield 'trop court → absent' => ['J.', null];
        yield 'vide → absent' => ['123', null];
    }

    #[DataProvider('names')]
    public function testPayerName(string $raw, ?string $expected): void
    {
        $this->assertSame($expected, CreateCheckoutUseCase::payerName($raw));
    }
}
