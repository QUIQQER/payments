<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Invoice
 */

namespace QUI\ERP\Accounting\Payments\Methods\Invoice;

use QUI;

/**
 * Class Payment
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Invoice\Payment
 */
class Payment extends QUI\ERP\Accounting\Payments\Api\AbstractPayment
{
    /**
     * @return array|string
     */
    public function getTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.invoice.title'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.invoice.description'
        );
    }
}
