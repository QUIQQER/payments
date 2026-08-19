<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use QUI\ERP\Accounting\Payments\Api\AbstractPayment;

class TestPaymentMethod extends AbstractPayment
{
    public function getTitle(): string
    {
        return 'Test title';
    }

    public function getDescription(): string
    {
        return 'Test description';
    }

    public function isSuccessful(string $hash): bool
    {
        return $hash === 'successful';
    }
}
