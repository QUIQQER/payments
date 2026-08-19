<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

class UniqueTestPaymentMethod extends TestPaymentMethod
{
    public function isUnique(): bool
    {
        return true;
    }
}
