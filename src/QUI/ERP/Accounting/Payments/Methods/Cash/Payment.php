<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Cash\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\Cash;

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
            'payment.cash.title'
        );
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.cash.description'
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

    /**
     * Return the extra text for the invoice
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary|QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
     * @return string
     */
    public function getInvoiceInformationText(
        QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary|QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
    ): string {
        return QUI::getLocale()->get('quiqqer/payments', 'invoice.information.text.cache');
    }
}
