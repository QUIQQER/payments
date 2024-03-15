<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Free\PaymentType
 */

namespace QUI\ERP\Accounting\Payments\Methods\Free;

use QUI;
use QUI\CRUD\Factory;
use QUI\ERP\Order\OrderInterface;

/**
 * Class PaymentType
 * - This class is a placeholder / helper class for the free payment
 * - if an order has no value of goods, this payment will be used
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Free\PaymentType
 */
class PaymentType extends QUI\ERP\Accounting\Payments\Types\Payment implements
    QUI\ERP\Accounting\Payments\Types\PaymentInterface
{
    public function __construct(protected $id, protected Factory $Factory)
    {
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $lg = 'quiqqer/payments';
        $Locale = QUI::getLocale();

        return [
            'title' => $Locale->get($lg, 'payment.free.title'),
            'description' => $Locale->get($lg, 'payment.free.description'),
            'workingTitle' => $Locale->get($lg, 'payment.free.workingTitle'),
            'paymentType' => false,
            'icon' => ''
        ];
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
     * @return int
     */
    public function getId()
    {
        return -1;
    }

    /**
     * Has the payment a payment fee?
     *
     * @return Bool
     */
    public function hasPaymentFee(): bool
    {
        return false;
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return array|string
     */
    public function getTitle(QUI\Locale $Locale = null): string
    {
        $PaymentType = $this->getPaymentType();

        if ($Locale !== null) {
            $PaymentType->setLocale($Locale);
        }

        return $PaymentType->getTitle();
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return array|string
     */
    public function getWorkingTitle(QUI\Locale $Locale = null): string
    {
        $PaymentType = $this->getPaymentType();

        if ($Locale !== null) {
            $PaymentType->setLocale($Locale);
        }

        return $PaymentType->getWorkingTitle();
    }

    /**
     * @param QUI\Locale|null $Locale
     * @return array|string
     */
    public function getDescription(QUI\Locale $Locale = null): string
    {
        $PaymentType = $this->getPaymentType();

        if ($Locale !== null) {
            $PaymentType->setLocale($Locale);
        }

        return $PaymentType->getDescription();
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return $this->getPaymentType()->getIcon();
    }

    /**
     * @return Payment
     */
    public function getPaymentType(): QUI\ERP\Accounting\Payments\Api\AbstractPayment
    {
        return new Payment();
    }

    /**
     * @param QUI\Interfaces\Users\User $User
     * @return bool
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User): bool
    {
        return true;
    }

    /**
     * @param OrderInterface $Order
     * @return string
     */
    public function getOrderInformationText(QUI\ERP\Order\OrderInterface $Order): string
    {
        return '';
    }
}
