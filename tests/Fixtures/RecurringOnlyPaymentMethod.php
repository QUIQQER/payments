<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

class RecurringOnlyPaymentMethod extends TestPaymentMethod
{
    public function supportsRecurringPaymentsOnly(): bool
    {
        return true;
    }
}
