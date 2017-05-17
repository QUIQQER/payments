<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Provider
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;

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
    public function getPayments()
    {
        return [
            QUI\ERP\Accounting\Payments\Methods\Cash\Payment::class,
            QUI\ERP\Accounting\Payments\Methods\Invoice\Payment::class,
            QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment::class,
        ];
    }
}
