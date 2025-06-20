<?php

/**
 * This file contains \QUI\ERP\Accounting\Payments\Api\Payment
 */

namespace QUI\ERP\Accounting\Payments\Api;

use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Accounting\Payments\Types\RecurringPaymentInterface;
use QUI\ERP\Order\AbstractOrder;

use function get_class;
use function is_a;
use function md5;

/**
 * Payment abstract class
 * This is the parent payment class for all payment methods
 *
 * @author www.pcsg.de (Henning Leutz)
 */
abstract class AbstractPayment implements PaymentsInterface
{
    /**
     * @var int
     */
    const SUCCESS_TYPE_PAY = 1;

    /**
     * @var int
     */
    const SUCCESS_TYPE_BILL = 2;

    /**
     * payment fields - extra fields for the payment / accounting
     *
     * @var array
     */
    protected array $paymentFields = [];

    /**
     * default settings
     *
     * @var array
     */
    protected array $defaults = [];

    /**
     * Locale object for the payment
     *
     * @var ?QUI\Locale
     */
    protected ?QUI\Locale $Locale = null;

    /**
     * Set the locale object to the payment
     *
     * @param QUI\Locale $Locale
     */
    public function setLocale(QUI\Locale $Locale): void
    {
        $this->Locale = $Locale;
    }

    /**
     * Return the Locale of the payment
     *
     * @return QUI\Locale
     */
    public function getLocale(): QUI\Locale
    {
        if ($this->Locale === null) {
            $this->Locale = QUI::getLocale();
        }

        return $this->Locale;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return md5(get_class($this));
    }

    /**
     * Return the class of the instance
     *
     * @return string
     */
    public function getClass(): string
    {
        return get_class($this);
    }

    /**
     * @return QUI\ERP\Enums\Payments\EN16931
     */
    public function getTypeCode(): QUI\ERP\Enums\Payments\EN16931
    {
        return QUI\ERP\Enums\Payments\EN16931::NOT_DEFINED;
    }

    /**
     * @return string
     */
    abstract public function getTitle(): string;

    /**
     * @return string
     */
    abstract public function getDescription(): string;

    /**
     * Is the payment successful?
     *
     * Successful = The payment process was executed correctly
     *
     * IMPORTANT: This does NOT mean that actual money was transferred!
     *
     * @param string $hash - Vorgangsnummer - hash number - procedure number
     * @return bool
     */
    abstract public function isSuccessful(string $hash): bool;

    /**
     * Is the payment approved?
     *
     * Approved = The payment amount is considered to be safe for payment
     *
     * IMPORTANT: This does NOT mean that actual money was transferred!
     *
     * @param string $hash - Vorgangsnummer - hash number - procedure number
     * @return bool
     */
    public function isApproved(string $hash): bool
    {
        return $this->isSuccessful($hash);
    }

    /**
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon(): string
    {
        return URL_OPT_DIR . 'quiqqer/payments/bin/payments/default.png';
    }

    /**
     * Return the payment as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'typeCode' => $this->getTypeCode()
        ];
    }

    /**
     * Is the payment a gateway payment?
     *
     * @return bool
     */
    public function isGateway(): bool
    {
        return false;
    }

    /**
     * Is the payment be visible in the frontend?
     * Every payment method can determine this by itself (API for developers)
     *
     * @param AbstractOrder $Order
     * @return bool
     */
    public function isVisible(AbstractOrder $Order): bool
    {
        return true;
    }

    /**
     * This flag indicates whether the payment module can be created more than once
     *
     * @return bool
     */
    public function isUnique(): bool
    {
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
     * Does the payment support recurring payments (e.g. for subscriptions)?
     *
     * @return bool
     */
    final public function supportsRecurringPayments(): bool
    {
        return is_a($this, RecurringPaymentInterface::class, true);
    }

    /**
     * Does the payment ONLY support recurring payments (e.g. for subscriptions)?
     *
     * @return bool
     */
    public function supportsRecurringPaymentsOnly(): bool
    {
        return false;
    }

    /**
     * Execute a refund
     *
     * @param QUI\ERP\Accounting\Payments\Transactions\Transaction $Transaction
     * @param float|int $amount
     * @param string $message
     * @param bool|string $hash - if a new hash will be used
     */
    public function refund(
        Transaction $Transaction,
        float | int $amount,
        string $message = '',
        bool | string $hash = false
    ): void {
        // you will find an example for a refund at
        // https://dev.quiqqer.com/quiqqer/payments-gateway/blob/master/src/QUI/ERP/Payments/Example/Payment.php
    }

    /**
     * If the Payment method is a payment gateway, it can return a gateway display
     *
     * @param AbstractOrder $Order
     * @param QUI\ERP\Order\Controls\AbstractOrderingStep|null $Step
     * @return string
     */
    public function getGatewayDisplay(
        AbstractOrder $Order,
        ?QUI\ERP\Order\Controls\AbstractOrderingStep $Step = null
    ): string {
        return '';
    }

    /**
     * Execute the request from the payment provider
     *
     * @param QUI\ERP\Accounting\Payments\Gateway\Gateway $Gateway
     */
    public function executeGatewayPayment(QUI\ERP\Accounting\Payments\Gateway\Gateway $Gateway)
    {
    }

    //region text messages

    /**
     * Return the extra text for the invoice
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary|QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
     * @return string
     */
    public function getInvoiceInformationText(
        QUI\ERP\Accounting\Invoice\Invoice | QUI\ERP\Accounting\Invoice\InvoiceTemporary | QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
    ): string {
        return '';
    }

    //endregion
}
