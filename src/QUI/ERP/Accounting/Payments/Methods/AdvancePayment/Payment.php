<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\AdvancePayment;

use QUI;
use QUI\ERP\Accounting\Payments\Payments;

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

    /**
     * @param $hash
     * @return bool
     */
    public function isSuccessful($hash)
    {
        return false;
    }

    /**
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return Payments::getInstance()->getHost().
               URL_OPT_DIR.
               'quiqqer/payments/bin/payments/Vorkasse.png';
    }
}
