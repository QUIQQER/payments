<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Free\PaymentType
 */

namespace QUI\ERP\Accounting\Payments\Methods\Free;

use QUI;

/**
 * Class PaymentType
 * - This class is a placeholder / helper class for the free payment
 * - if an order has no value of goods, this payment will be used
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Free\PaymentType
 */
class PaymentType extends QUI\QDOM
{
    /**
     * @return array
     */
    public function toArray()
    {
        $lg     = 'quiqqer/payments';
        $Locale = QUI::getLocale();

        return [
            'title'        => $Locale->get($lg, 'payment.free.title'),
            'description'  => $Locale->get($lg, 'payment.free.description'),
            'workingTitle' => $Locale->get($lg, 'payment.free.workingTitle'),
            'paymentType'  => false,
            'icon'         => ''
        ];
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return true;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return -1;
    }

    /**
     * @return array|string
     */
    public function getTitle()
    {
        return $this->getPaymentType()->getTitle();
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getPaymentType()->getDescription();
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->getPaymentType()->getIcon();
    }

    /**
     * @return Payment
     */
    public function getPaymentType()
    {
        return new Payment();
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User)
    {
        return true;
    }
}
