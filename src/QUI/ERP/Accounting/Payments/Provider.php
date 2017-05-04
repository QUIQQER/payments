<?php

/**
 * This file contains QUI\ERP\Accounting\Payments
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;

/**
 * Class Provider
 *
 * @package QUI\ERP\Accounting\Payments
 */
class Provider
{
    /**
     * @return array
     */
    public function getPayments()
    {
        return [
            QUI\ERP\Accounting\Payments\Methods\Cash\Payment::class,
            QUI\ERP\Accounting\Payments\Methods\Invoice\Payment::class
        ];
    }
}
