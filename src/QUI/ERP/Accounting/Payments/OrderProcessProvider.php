<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\OrderProcessProvider
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
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
    protected ?AbstractPayment $Payment = null;

    /**
     * @param OrderProcessSteps $OrderProcessSteps
     * @param OrderProcess $OrderProcess
     *
     * @throws \QUI\Exception
     * @throws \QUI\ERP\Order\Exception
     */
    public function initSteps(OrderProcessSteps $OrderProcessSteps, OrderProcess $OrderProcess): void
    {
        $orderId = null;
        $Order = null;

        if ($OrderProcess->getOrder()) {
            $Order = $OrderProcess->getOrder();
            $orderId = $Order->getId();
        }

        $OrderProcessSteps->append(
            new Order\Payment([
                'orderId' => $orderId,
                'Order' => $Order,
                'priority' => 30
            ])
        );
    }

    /**
     * @param AbstractOrder $Order
     * @return string
     *
     * @throws Exception
     */
    public function onOrderStart(AbstractOrder $Order): string
    {
        if ($Order->isSuccessful()) {
            $this->currentStatus = self::PROCESSING_STATUS_FINISH;
            return $this->currentStatus;
        }

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
    public function getDisplay(AbstractOrder $Order, $Step = null): string
    {
        if ($this->Payment === null) {
            return '';
        }

        try {
            return $this->Payment->getGatewayDisplay($Order, $Step);
        } catch (QUI\ERP\Order\ProcessingException $Exception) {
            $this->hasErrors = true;

            return '<div class="message-error">' . $Exception->getMessage() . '</div>';
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            $this->hasErrors = true;

            return '<div class="message-error">' .
                QUI::getLocale()->get('quiqqer/order', 'exception.processing.error') .
                '</div>';
        }
    }
}
