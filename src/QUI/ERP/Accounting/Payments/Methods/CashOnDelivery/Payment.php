<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\CashOnDelivery\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\CashOnDelivery;

use QUI;
use QUI\ERP\Accounting\Payments\Payments;

/**
 * Class Payment
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Cash\Payment
 */
class Payment extends QUI\ERP\Accounting\Payments\Api\AbstractPayment
{
    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.cashOnDelivery.title'
        );
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.cashOnDelivery.description'
        );
    }

    /**
     * @return bool
     */
    public function isGateway(): bool
    {
        return false;
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function isSuccessful(string $hash): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public function refundSupport(): bool
    {
        return true;
    }

    /**
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon(): string
    {
        return Payments::getInstance()->getHost() .
            URL_OPT_DIR .
            'quiqqer/payments/bin/payments/Bar.jpg';
    }
}
