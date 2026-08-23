<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Factory;

class TestablePayments extends Payments
{
    public function __construct(private readonly Factory $PaymentFactory)
    {
    }

    protected function getPaymentFactory(): Factory
    {
        return $this->PaymentFactory;
    }
}
