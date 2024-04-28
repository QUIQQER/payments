<?php

namespace QUI\ERP\Accounting\Payments\Types;

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
     * Create a Subscription from a (temporary) Order
     *
     * @param AbstractOrder $Order
     * @return string|null
     */
    public function createSubscription(AbstractOrder $Order): ?string;

    /**
     * Capture subscription amount based on an Invoice
     *
     * @param Invoice $Invoice
     * @return void
     */
    public function captureSubscription(Invoice $Invoice): void;

    /**
     * Cancel a Subscription
     *
     * This *permanently* cancels the subscription and prevents any future payments / automated payment collections.
     *
     * @param int|string $subscriptionId
     * @param string $reason (optional) - The reason why the subscription is cancelled
     * @return void
     */
    public function cancelSubscription(int|string $subscriptionId, string $reason = ''): void;

    /**
     * Suspend a Subscription
     *
     * This *temporarily* suspends the automated collection of payments until explicitly resumed.
     *
     * @param int|string $subscriptionId
     * @param string|null $note (optional) - Suspension note
     * @return void
     */
    public function suspendSubscription(int|string $subscriptionId, string $note = null): void;

    /**
     * Resume a suspended Subscription
     *
     * This resumes automated collection of payments of a previously superdense Subscription.
     *
     * @param int|string $subscriptionId
     * @param string|null $note (optional) - Resume note
     * @return void
     */
    public function resumeSubscription(int|string $subscriptionId, string $note = null): void;

    /**
     * Checks if a subscription is currently suspended
     *
     * @param int|string $subscriptionId
     * @return bool
     */
    public function isSuspended(int|string $subscriptionId): bool;

    /**
     * Sets a subscription as inactive (on the side of this QUIQQER system only!)
     *
     * IMPORTANT: This does NOT mean that the corresponding subscription at the payment provider
     * side is cancelled. If you want to do this please use cancelSubscription() !
     *
     * @param int|string $subscriptionId
     * @return void
     */
    public function setSubscriptionAsInactive(int|string $subscriptionId): void;

    /**
     * Can the Subscription of this payment method be edited
     * regarding essential data like invoice frequency, amount etc.?
     *
     * @return bool
     */
    public function isSubscriptionEditable(): bool;

    /**
     * Check if a Subscription is associated with an order and
     * return its ID (= identification at the payment method side; e.g. PayPal)
     *
     * @param AbstractOrder $Order
     * @return int|string|false - ID or false of no ID associated
     */
    public function getSubscriptionIdByOrder(AbstractOrder $Order): bool|int|string;

    /**
     * Checks if the subscription is active at the payment provider side
     *
     * @param int|string $subscriptionId
     * @return bool
     */
    public function isSubscriptionActiveAtPaymentProvider(int|string $subscriptionId): bool;

    /**
     * Checks if the subscription is active at QUIQQER
     *
     * @param int|string $subscriptionId - Payment provider subscription ID
     * @return bool
     */
    public function isSubscriptionActiveAtQuiqqer(int|string $subscriptionId): bool;

    /**
     * Get IDs of all subscriptions
     *
     * @param bool $includeInactive (optional) - Include inactive subscriptions [default: false]
     * @return int[]
     */
    public function getSubscriptionIds(bool $includeInactive = false): array;

    /**
     * Get global processing ID of a subscription
     *
     * @param int|string $subscriptionId
     * @return string|false
     */
    public function getSubscriptionGlobalProcessingId(int|string $subscriptionId): bool|string;
}
