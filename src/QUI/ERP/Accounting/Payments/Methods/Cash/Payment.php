<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Cash\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\Cash;

use QUI;
use QUI\ERP\Order\AbstractOrder;

/**
 * Class Payment
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Cash\Payment
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
            'payment.cash.title'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.cash.description'
        );
    }

    /**
     * @return bool
     */
    public function isGateway()
    {
        return false;
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function isSuccessful($hash)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function refundSupport()
    {
        return true;
    }

    /**
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return URL_OPT_DIR.'quiqqer/payments/bin/payments/Bar.jpg';
    }
}
