<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Payments\Methods\Standard\Payment;

class StandardPaymentTest extends TestCase
{
    public function testStandardPaymentExposesItsApiMetadata(): void
    {
        $Payment = new Payment();
        $data = $Payment->toArray();

        self::assertNotSame('', $Payment->getName());
        self::assertSame($Payment->getName(), $data['name']);
        self::assertSame($Payment->getTitle(), $data['title']);
        self::assertSame($Payment->getDescription(), $data['description']);
        self::assertFalse($Payment->isGateway());
    }

    public function testStandardPaymentSupportsProviderlessSubscriptions(): void
    {
        $Payment = new Payment();

        self::assertTrue($Payment->supportsRecurringPayments());
        self::assertFalse($Payment->supportsRecurringPaymentsOnly());
        self::assertTrue($Payment->isSubscriptionEditable());
        self::assertSame([], $Payment->getSubscriptionIds());
    }
}
