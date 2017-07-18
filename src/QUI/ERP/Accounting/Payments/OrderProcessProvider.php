<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\OrderProcessProvider
 */

namespace QUI\ERP\Accounting\Payments;

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
}
