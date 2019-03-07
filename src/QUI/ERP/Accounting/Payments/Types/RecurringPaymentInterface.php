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
     * Create a Billing Agreement from a (temporary) Order
     *
     * @param AbstractOrder $Order
     * @return void
     */
    public function createBillingAgreement(AbstractOrder $Order);

    /**
     * Bills the balance for an agreement based on an Invoice
     *
     * @param Invoice $Invoice
     * @return void
     */
    public function billBillingAgreementBalance(Invoice $Invoice);

    /**
     * Cancel a Billing Agreement
     *
     * @param int|string $billingAgreementId
     * @return void
     */
    public function cancelBillingAgreement($billingAgreementId);

    /**
     * Can the Billing Agreement of this payment method be edited
     * regarding essential data like invoice frequency, amount etc.?
     *
     * @return bool
     */
    public function isBillingAgreementEditable();

    /**
     * Check if a Billing Agreement is associated with an order and
     * return its ID (= identification at the payment method side; e.g. PayPal)
     *
     * @param AbstractOrder $Order
     * @return int|string|false - ID or false of no ID associated
     */
    public function getBillingAgreementIdByOrder(AbstractOrder $Order);
}
