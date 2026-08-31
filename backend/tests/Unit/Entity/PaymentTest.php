<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Enum\PaymentState;
use App\Entity\License;
use App\Entity\Payment;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    public function testToArrayAndAccessors(): void
    {
        $license = (new License())->setSeason('2026-2027');

        $payment = (new Payment())
            ->setLicense($license)
            ->setAmount(12000)
            ->setState(PaymentState::AUTHORIZED)
            ->setHelloAssoCheckoutIntentId(111)
            ->setHelloAssoOrderId(222)
            ->setHelloAssoPaymentId(333)
            ->setPaymentReceiptUrl('https://helloasso.com/receipt/333')
            ->setRawPayload(['eventType' => 'Payment', 'data' => ['id' => 333]]);

        $array = $payment->toArray();

        $this->assertSame(12000, $array['amount']);
        $this->assertSame('authorized', $array['state']);
        $this->assertSame(111, $array['helloAssoCheckoutIntentId']);
        $this->assertSame(222, $array['helloAssoOrderId']);
        $this->assertSame(333, $array['helloAssoPaymentId']);
        $this->assertSame('https://helloasso.com/receipt/333', $array['paymentReceiptUrl']);

        $this->assertSame($license, $payment->getLicense());
        $this->assertSame(['eventType' => 'Payment', 'data' => ['id' => 333]], $payment->getRawPayload());
    }
}
