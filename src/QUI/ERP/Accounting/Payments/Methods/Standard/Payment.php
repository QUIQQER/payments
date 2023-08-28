<?php

namespace QUI\ERP\Accounting\Payments\Methods\Standard;

use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
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
            'payment.standard.title'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.standard.description'
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
        return URL_OPT_DIR . 'quiqqer/payments/bin/payments/Rechnung.jpg';
    }

    /**
     * Create a Subscription from a (temporary) Order
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
     * Suspend a Subscription
     *
     * This *temporarily* suspends the automated collection of payments until explicitly resumed.
     *
     * @param int|string $subscriptionId
     * @param string $note (optional) - Suspension note
     * @return void
     */
    public function suspendSubscription($subscriptionId, string $note = null)
    {
        // Payment by invoice does not need to have/suspend a subscription with any service
    }

    /**
     * Resume a suspended Subscription
     *
     * This resumes automated collection of payments of a previously supsendes Subscription.
     *
     * @param int|string $subscriptionId
     * @param string $note (optional) - Resume note
     * @return void
     */
    public function resumeSubscription($subscriptionId, string $note = null)
    {
        // Payment by invoice does not need to have/resume a subscription with any service
    }

    /**
     * Checks if a subscription is currently suspended
     *
     * @param int|string $subscriptionId
     * @return bool
     */
    public function isSuspended($subscriptionId)
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
    public function setSubscriptionAsInactive($subscriptionId)
    {
        // Since payment by invoice is not connected to a external service, there is nothing to set as inactive
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

    /**
     * Checks if the subscription is active at the payment provider side
     *
     * @param string|int $subscriptionId
     * @return bool
     */
    public function isSubscriptionActiveAtPaymentProvider($subscriptionId)
    {
        return true;
    }

    /**
     * Checks if the subscription is active at QUIQQER
     *
     * @param string|int $subscriptionId - Payment provider subscription ID
     * @return bool
     */
    public function isSubscriptionActiveAtQuiqqer($subscriptionId)
    {
        return true;
    }

    /**
     * Get IDs of all subscriptions
     *
     * @param bool $includeInactive (optional) - Include inactive subscriptions [default: false]
     * @return int[]
     */
    public function getSubscriptionIds($includeInactive = false)
    {
        // There are no external subscription IDs
        return [];
    }

    /**
     * Get global processing ID of a subscription
     *
     * @param string|int $subscriptionId
     * @return string|false
     */
    public function getSubscriptionGlobalProcessingId($subscriptionId)
    {
        // Since there are no external subscription IDs, nothing can be returned here
        return false;
    }
}
