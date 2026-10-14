<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use QUI\ERP\Accounting\Payments\McpProvider;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Factory;

class TestableMcpProvider extends McpProvider
{
    public function __construct(private readonly Factory $Factory, private readonly Payments $Payments)
    {
    }

    protected function getFactory(): Factory
    {
        return $this->Factory;
    }

    protected function getPayments(): Payments
    {
        return $this->Payments;
    }
}
