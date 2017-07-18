<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\OrderProcessProvider
 */

namespace QUI\ERP\Accounting\Payments;

use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\Utils\OrderProcessSteps;

/**
 * Class OrderProcessProvider
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class OrderProcessProvider extends AbstractOrderProcessProvider
{
    /**
     * @param OrderProcessSteps $OrderProcessSteps
     * @param OrderProcess $Order
     */
    public function initSteps(OrderProcessSteps $OrderProcessSteps, OrderProcess $Order)
    {
        $OrderProcessSteps->append(
            new Order\Payment(array(
                'orderId'  => $Order->getOrder()->getId(),
                'Order'    => $Order->getOrder(),
                'priority' => 3
            ))
        );
    }

    /**
     * @param AbstractOrder $Order
     * @return int
     */
    public function onOrderStart(AbstractOrder $Order)
    {
        $Payment = $Order->getPayment()->getPaymentType();

        if ($Payment->isGateway()) {
            return self::PROCESSING_STATUS_PROCESSING;
        }

        return self::PROCESSING_STATUS_FINISH;
    }
}
