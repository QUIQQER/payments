<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use QUI\ERP\Accounting\Payments\Types\Payment;

class RecordingPayment extends Payment
{
    /** @var array<string, array<string, string>> */
    public array $localeChanges = [];

    protected function setPaymentLocale(string $var, array $title): void
    {
        $this->localeChanges[$var] = $title;
    }
}
