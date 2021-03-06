<?php

namespace QUI\ERP\Accounting\Payments\Types;

use QUI;

/**
 * Interface PaymentInterface
 *
 * @package QUI\ERP\Accounting\Payments\Types
 */
interface PaymentInterface
{
    //region general

    /**
     * @return integer
     */
    public function getId();

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null);

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getDescription($Locale = null);

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getWorkingTitle($Locale = null);

    //endregion

    /**
     * @return array
     */
    public function toArray();

    /**
     * @param string $hash - order hash
     * @return bool
     */
    public function isSuccessful($hash);

    /**
     * @return \QUI\ERP\Accounting\Payments\Api\AbstractPayment
     */
    public function getPaymentType();

    /**
     * @return bool
     */
    public function hasPaymentFee();

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User);

    /**
     * @param QUI\ERP\Order\OrderInterface $Order
     * @return string
     */
    public function getOrderInformationText(QUI\ERP\Order\OrderInterface $Order);
}
