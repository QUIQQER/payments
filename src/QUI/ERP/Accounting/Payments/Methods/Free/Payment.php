<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Free\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\Free;

use QUI;

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
     * @return array|string
     */
    public function getTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.free.title'
        );
    }

    /**
     * @return array|string
     */
    public function getWorkingTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.free.workingTitle'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.free.description'
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
        try {
            $Order       = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($hash);
            $Calculation = $Order->getPriceCalculation();

            if ($Calculation->getSum() === 0) {
                return true;
            }
        } catch (\Exception $Exception) {
        }

        return false;
    }

    /**
     * @return bool
     */
    public function refundSupport()
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
        return URL_OPT_DIR.'quiqqer/payments/bin/payments/Bar.jpg';
    }
}
