<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use QUI\ERP\Accounting\Payments\Api\AbstractPaymentProvider;

class InstallPaymentProvider extends AbstractPaymentProvider
{
    /**
     * @return list<class-string>
     */
    public function getPaymentTypes(): array
    {
        return [
            TestPaymentMethod::class,
            UniqueTestPaymentMethod::class
        ];
    }

    /**
     * @return list<class-string|array<string, mixed>>
     */
    public function getPaymentTypesToCreateOnInstall(): array
    {
        return [
            [
                'payment_type' => TestPaymentMethod::class,
                'title' => [
                    'de' => 'Installierter Testtitel',
                    'en' => 'Installed test title'
                ],
                'workingTitle' => '[quiqqer/payment-test] payment.workingTitle',
                'description' => '[quiqqer/payment-test] payment.description',
                'orderInformation' => [
                    'de' => 'Bestellinformation',
                    'en' => 'Order information'
                ],
                'priority' => 17,
                'paymentFee' => '2.50',
                'active' => 1
            ],
            UniqueTestPaymentMethod::class
        ];
    }
}
