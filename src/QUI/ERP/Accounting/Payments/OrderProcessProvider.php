<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\OrderProcessProvider
 */

namespace QUI\ERP\Accounting\Payments;

use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Controls\AbstractOrderingStep;
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
     * @var null|AbstractPayment
     */
    protected $Payment = null;

    /**
     * @param OrderProcessSteps $OrderProcessSteps
     * @param OrderProcess $Process
     *
     * @throws \QUI\Exception
     * @throws \QUI\ERP\Order\Exception
     */
    public function initSteps(OrderProcessSteps $OrderProcessSteps, OrderProcess $Process)
    {
        $orderId = null;
        $Order   = null;

        if ($Process->getOrder()) {
            $Order   = $Process->getOrder();
            $orderId = $Order->getId();
        }

        $OrderProcessSteps->append(
            new Order\Payment(array(
                'orderId'  => $orderId,
                'Order'    => $Order,
                'priority' => 30
            ))
        );
    }

    /**
     * @param AbstractOrder $Order
     *
     * @return string
     *
     * @throws \QUI\ERP\Accounting\Payments\Exception
     */
    public function onOrderStart(AbstractOrder $Order)
    {
        $this->Payment = $Order->getPayment()->getPaymentType();

        if ($this->Payment->isGateway()) {
            $this->currentStatus = self::PROCESSING_STATUS_PROCESSING;

            return $this->currentStatus;
        }

        $this->currentStatus = self::PROCESSING_STATUS_FINISH;

        return $this->currentStatus;
    }

    /**
     * @param AbstractOrder $Order
     * @param AbstractOrderingStep|null $Step
     * @return string
     */
    public function getDisplay(AbstractOrder $Order, $Step = null)
    {
        if ($this->Payment === null) {
            return '';
        }

        return $this->Payment->getGatewayDisplay($Order, $Step);
    }
}
