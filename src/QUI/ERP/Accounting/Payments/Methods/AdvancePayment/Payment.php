<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\AdvancePayment;

use QUI;

/**
 * Class Payment
 * - Vorkasse
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
            'payment.advanced.payment.title'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.advanced.payment.description'
        );
    }
}
