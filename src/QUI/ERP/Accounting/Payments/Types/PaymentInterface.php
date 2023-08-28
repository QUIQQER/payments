<?php

namespace QUI\ERP\Accounting\Payments\Types;

use QUI;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;

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
    public function getTitle(QUI\Locale $Locale = null): string;

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getDescription(QUI\Locale $Locale = null): string;

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getWorkingTitle(QUI\Locale $Locale = null): string;

    //endregion

    /**
     * @return array
     */
    public function toArray(): array;

    /**
     * @param string $hash - order hash
     * @return bool
     */
    public function isSuccessful(string $hash): bool;

    /**
     * @return AbstractPayment
     */
    public function getPaymentType(): AbstractPayment;

    /**
     * @return QUI\ERP\Currency\Currency[]
     */
    public function getSupportedCurrencies(): array;

    /**
     * @return bool
     */
    public function hasPaymentFee(): bool;

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User): bool;

    /**
     * @param QUI\ERP\Order\OrderInterface $Order
     * @return string
     */
    public function getOrderInformationText(QUI\ERP\Order\OrderInterface $Order): string;
}
