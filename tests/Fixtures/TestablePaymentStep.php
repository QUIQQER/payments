<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use QUI\ERP\Accounting\Payments\Order\Payment as PaymentStep;
use QUI\ERP\Accounting\Payments\Types\Payment;

class TestablePaymentStep extends PaymentStep
{
    /** @var null|list<Payment> */
    public ?array $controlledPaymentList = null;

    /**
     * @return list<Payment>
     */
    public function realPaymentList(): array
    {
        return parent::getPaymentList();
    }

    protected function getPaymentList(): array
    {
        if ($this->controlledPaymentList !== null) {
            return $this->controlledPaymentList;
        }

        return parent::getPaymentList();
    }
}
