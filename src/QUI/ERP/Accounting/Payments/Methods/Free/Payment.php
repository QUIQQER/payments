<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Free\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\Free;

use Exception;
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
     * free payment id
     */
    const ID = -1;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.free.title'
        );
    }

    /**
     * @return string
     */
    public function getWorkingTitle(): string
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.free.workingTitle'
        );
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.free.description'
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
        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($hash);
            $Calculation = $Order->getPriceCalculation();

            if ($Calculation->getSum()->value() === 0) {
                return true;
            }
        } catch (Exception) {
        }

        return false;
    }

    /**
     * @return bool
     */
    public function refundSupport(): bool
    {
        return false;
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
            'quiqqer/payments/bin/payments/Free.png';
    }
}
