<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Provider
 */

namespace QUI\ERP\Accounting\Payments;

/**
 * Class Provider
 *
 * @package QUI\ERP\Accounting\Payments
 */
class Provider extends Api\AbstractPaymentProvider
{
    /**
     * @return array
     */
    public function getPaymentTypes(): array
    {
        return [
            Methods\Cash\Payment::class,
            Methods\Invoice\Payment::class,
            Methods\AdvancePayment\Payment::class,
            Methods\Standard\Payment::class,
        ];
    }
}
