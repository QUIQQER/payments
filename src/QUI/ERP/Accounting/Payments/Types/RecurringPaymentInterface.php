<?php

namespace QUI\ERP\Accounting\Payments\Types;

use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Accounting\Invoice\Invoice;

/**
 * Interface RecurringPaymentInterface
 *
 * Interface for all Payment classes that offer recurring payments
 */
interface RecurringPaymentInterface
{
    /**
     * Create a Scubscription from a (temporary) Order
     *
     * @param AbstractOrder $Order
     * @return void
     */
    public function createSubscription(AbstractOrder $Order);

    /**
     * Capture subscription amount based on an Invoice
     *
     * @param Invoice $Invoice
     * @return void
     */
    public function captureSubscription(Invoice $Invoice);

    /**
     * Cancel a Subscription
     *
     * This *permanently* cancels the subscription and prevents any future payments / automated payment collections.
     *
     * @param int|string $subscriptionId
     * @param string $reason (optional) - The reason why the subscription is cancelled
     * @return void
     */
    public function cancelSubscription($subscriptionId, $reason = '');

    /**
     * Suspend a Subscription
     *
     * This *temporarily* suspends the automated collection of payments until explicitly resumed.
     *
     * @param int|string $subscriptionId
     * @param string $note (optional) - Suspension note
     * @return void
     */
    public function suspendSubscription($subscriptionId, string $note = null);

    /**
     * Resume a suspended Subscription
     *
     * This resumes automated collection of payments of a previously supsendes Subscription.
     *
     * @param int|string $subscriptionId
     * @param string $note (optional) - Resume note
     * @return void
     */
    public function resumeSubscription($subscriptionId, string $note = null);

    /**
     * Checks if a subscription is currently suspended
     *
     * @param int|string $subscriptionId
     * @return bool
     */
    public function isSuspended($subscriptionId);

    /**
     * Sets a subscription as inactive (on the side of this QUIQQER system only!)
     *
     * IMPORTANT: This does NOT mean that the corresponding subscription at the payment provider
     * side is cancelled. If you want to do this please use cancelSubscription() !
     *
     * @param $subscriptionId
     * @return void
     */
    public function setSubscriptionAsInactive($subscriptionId);

    /**
     * Can the Subscription of this payment method be edited
     * regarding essential data like invoice frequency, amount etc.?
     *
     * @return bool
     */
    public function isSubscriptionEditable();

    /**
     * Check if a Subscription is associated with an order and
     * return its ID (= identification at the payment method side; e.g. PayPal)
     *
     * @param AbstractOrder $Order
     * @return int|string|false - ID or false of no ID associated
     */
    public function getSubscriptionIdByOrder(AbstractOrder $Order);

    /**
     * Checks if the subscription is active at the payment provider side
     *
     * @param string|int $subscriptionId
     * @return bool
     */
    public function isSubscriptionActiveAtPaymentProvider($subscriptionId);

    /**
     * Checks if the subscription is active at QUIQQER
     *
     * @param string|int $subscriptionId - Payment provider subscription ID
     * @return bool
     */
    public function isSubscriptionActiveAtQuiqqer($subscriptionId);

    /**
     * Get IDs of all subscriptions
     *
     * @param bool $includeInactive (optional) - Include inactive subscriptions [default: false]
     * @return int[]
     */
    public function getSubscriptionIds($includeInactive = false);

    /**
     * Get global processing ID of a subscription
     *
     * @param string|int $subscriptionId
     * @return string|false
     */
    public function getSubscriptionGlobalProcessingId($subscriptionId);
}
