<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Provider
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\ERP\Accounting\Payments\Methods;

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
    public function getPaymentTypes()
    {
        return [
            Methods\Cash\Payment::class,
            Methods\Invoice\Payment::class,
            Methods\AdvancePayment\Payment::class,
        ];
    }
}
