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
use QUI\ERP\Accounting\Payments\Types\RecurringPaymentInterface;
use QUI\ERP\Order\AbstractOrder;

/**
 * Class Payment
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Invoice\Payment
 */
class Payment extends QUI\ERP\Accounting\Payments\Api\AbstractPayment implements RecurringPaymentInterface
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

    /**
     * Create a Scubscription from a (temporary) Order
     *
     * @param AbstractOrder $Order
     * @return void
     */
    public function createSubscription(AbstractOrder $Order)
    {
        // Payment by invoice does not need to create a subscription with any service
    }

    /**
     * Capture subscription amount based on an Invoice
     *
     * @param Invoice $Invoice
     * @return void
     */
    public function captureSubscription(Invoice $Invoice)
    {
        // Invoice payment is manually added
    }

    /**
     * Cancel a Subscription
     *
     * @param int|string $subscriptionId
     * @param string $reason (optional) - The reason why the subscription is cancelled
     * @return void
     */
    public function cancelSubscription($subscriptionId, $reason = '')
    {
        // Payment by invoice does not need to have/cancel a subscription with any service
    }

    /**
     * Can the Subscription of this payment method be edited
     * regarding essential data like invoice frequency, amount etc.?
     *
     * @return bool
     */
    public function isSubscriptionEditable()
    {
        return true;
    }

    /**
     * Check if a Subscription is associated with an order and
     * return its ID (= identification at the payment method side; e.g. PayPal)
     *
     * @param AbstractOrder $Order
     * @return int|string|false - ID or false of no ID associated
     */
    public function getSubscriptionIdByOrder(AbstractOrder $Order)
    {
        return false;
    }
}
