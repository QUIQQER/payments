<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\Invoice\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\Invoice;

use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\InvoiceView;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Class Payment
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Invoice\Payment
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
            'payment.invoice.title'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.invoice.description'
        );
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function isSuccessful($hash)
    {
        return true;
    }

    /**
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return URL_OPT_DIR.'quiqqer/payments/bin/payments/Rechnung.jpg';
    }

    /**
     * Does the payment support recurring payments (e.g. for subscriptions)?
     *
     * @return bool
     */
    public function supportsRecurringPayments()
    {
        return true;
    }

    /**
     * Return an extra invoice text
     *
     * @param Invoice|InvoiceTemporary|InvoiceView $Invoice
     * @return mixed|string
     */
    public function getInvoiceInformationText($Invoice)
    {
        if ($Invoice->isPaid()) {
            return QUI::getLocale()->get(
                'quiqqer/payments',
                'text.invoice.information.for.invoicePayment.paid'
            );
        }

        $timeForPayment = InvoiceUtils::getInvoiceTimeForPaymentDate($Invoice);

        // today
        if (date('Y-m-d', $timeForPayment) === date('Y-m-d')) {
            return QUI::getLocale()->get(
                'quiqqer/payments',
                'text.invoice.information.for.invoicePayment.pay.now'
            );
        }

        // format time for payment
        $Locale    = $Invoice->getCustomer()->getLocale();
        $Formatter = $Locale->getDateFormatter();

        return QUI::getLocale()->get(
            'quiqqer/payments',
            'text.invoice.information.for.invoicePayment.pay.date',
            ['date' => $Formatter->format($timeForPayment)]
        );
    }
}
