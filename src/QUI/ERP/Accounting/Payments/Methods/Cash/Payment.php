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
//
//    /**
//     * @param AbstractOrder $Order
//     * @return string
//     */
//    public function getGatewayDisplay(AbstractOrder $Order)
//    {
//        return '<div>hier kann ganz viel kommen</div>';
//    }
}
