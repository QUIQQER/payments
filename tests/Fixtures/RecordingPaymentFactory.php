<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use QUI\ERP\Accounting\Payments\Types\Factory;

class RecordingPaymentFactory extends Factory
{
    /** @var array<string, array<string, string>|string> */
    public array $createdLocales = [];

    public function __construct()
    {
        parent::__construct();

        $this->Events->removeEvent('onCreateEnd');
    }

    protected function createPaymentLocale($var, array|string $title): void
    {
        $this->createdLocales[$var] = $title;
    }
}
