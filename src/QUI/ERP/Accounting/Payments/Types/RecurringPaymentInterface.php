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
     * @param int|string $subscriptionId
     * @param string $reason (optional) - The reason why the subscription is cancelled
     * @return void
     */
    public function cancelSubscription($subscriptionId, $reason = '');

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
}
