<?php

namespace QUI\ERP\Accounting\Payments\Methods\Invoice;

use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\InvoiceView;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Accounting\Payments\Types\RecurringPaymentInterface;
use QUI\ERP\Exception;
use QUI\ERP\Order\AbstractOrder;

use function date;

/**
 * Class Payment
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Invoice\Payment
 */
class Payment extends QUI\ERP\Accounting\Payments\Api\AbstractPayment implements RecurringPaymentInterface
{
    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.invoice.title'
        );
    }

    /**
     * @return string
     */
    public function getDescription(): string
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
    public function isSuccessful(string $hash): bool
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
        return URL_OPT_DIR . 'quiqqer/payments/bin/payments/Rechnung.jpg';
    }

    /**
     * Return an extra invoice text
     *
     * @param Invoice|InvoiceTemporary|InvoiceView $Invoice
     * @return string
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getInvoiceInformationText(Invoice|InvoiceTemporary|InvoiceView $Invoice): string
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
        $Locale = $Invoice->getCustomer()->getLocale();
        $Formatter = $Locale->getDateFormatter();

        return QUI::getLocale()->get(
            'quiqqer/payments',
            'text.invoice.information.for.invoicePayment.pay.date',
            ['date' => $Formatter->format($timeForPayment)]
        );
    }

    /**
     * Create a Subscription from a (temporary) Order
     *
     * @param AbstractOrder $Order
     * @return string|null
     */
    public function createSubscription(AbstractOrder $Order): ?string
    {
        // Payment by invoice does not need to create a subscription with any service
        return null;
    }

    /**
     * Capture subscription amount based on an Invoice
     *
     * @param Invoice $Invoice
     * @return void
     */
    public function captureSubscription(Invoice $Invoice): void
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
    public function cancelSubscription(int|string $subscriptionId, string $reason = ''): void
    {
        // Payment by invoice does not need to have/cancel a subscription with any service
    }

    /**
     * Suspend a Subscription
     *
     * This *temporarily* suspends the automated collection of payments until explicitly resumed.
     *
     * @param int|string $subscriptionId
     * @param string|null $note (optional) - Suspension note
     * @return void
     */
    public function suspendSubscription(int|string $subscriptionId, string $note = null): void
    {
        // Payment by invoice does not need to have/suspend a subscription with any service
    }

    /**
     * Resume a suspended Subscription
     *
     * This resumes automated collection of payments of a previously suspended subscription.
     *
     * @param int|string $subscriptionId
     * @param string|null $note (optional) - Resume note
     * @return void
     */
    public function resumeSubscription(int|string $subscriptionId, string $note = null): void
    {
        // Payment by invoice does not need to have/resume a subscription with any service
    }

    /**
     * Checks if a subscription is currently suspended
     *
     * @param int|string $subscriptionId
     * @return bool
     */
    public function isSuspended(int|string $subscriptionId): bool
    {
        // Payment by invoice cannot be suspended
        return false;
    }

    /**
     * Sets a subscription as inactive (on the side of this QUIQQER system only!)
     *
     * IMPORTANT: This does NOT mean that the corresponding subscription at the payment provider
     * side is cancelled. If you want to do this please use cancelSubscription() !
     *
     * @param $subscriptionId
     * @return void
     */
    public function setSubscriptionAsInactive($subscriptionId): void
    {
        // Since payment by invoice is not connected to an external service, there is nothing to set as inactive
    }

    /**
     * Can the Subscription of this payment method be edited
     * regarding essential data like invoice frequency, amount etc.?
     *
     * @return bool
     */
    public function isSubscriptionEditable(): bool
    {
        return true;
    }

    /**
     * Check if a Subscription is associated with an order and
     * return its ID (= identification at the payment method side; e.g. PayPal)
     *
     * @param AbstractOrder $Order
     * @return false - ID or false of no ID associated
     */
    public function getSubscriptionIdByOrder(AbstractOrder $Order): bool
    {
        return false;
    }

    /**
     * Checks if the subscription is active at the payment provider side
     *
     * @param int|string $subscriptionId
     * @return bool
     */
    public function isSubscriptionActiveAtPaymentProvider(int|string $subscriptionId): bool
    {
        return true;
    }

    /**
     * Checks if the subscription is active at QUIQQER
     *
     * @param int|string $subscriptionId - Payment provider subscription ID
     * @return bool
     */
    public function isSubscriptionActiveAtQuiqqer(int|string $subscriptionId): bool
    {
        return true;
    }

    /**
     * Get IDs of all subscriptions
     *
     * @param bool $includeInactive (optional) - Include inactive subscriptions [default: false]
     * @return int[]
     */
    public function getSubscriptionIds(bool $includeInactive = false): array
    {
        // There are no external subscription IDs
        return [];
    }

    /**
     * Get global processing ID of a subscription
     *
     * @param int|string $subscriptionId
     * @return false
     */
    public function getSubscriptionGlobalProcessingId(int|string $subscriptionId): bool
    {
        // Since there are no external subscription IDs, nothing can be returned here
        return false;
    }
}
