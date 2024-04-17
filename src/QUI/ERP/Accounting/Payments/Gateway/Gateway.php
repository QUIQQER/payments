<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Gateway\Gateway
 */

namespace QUI\ERP\Accounting\Payments\Gateway;

use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Factory as Transactions;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;

use function http_build_query;
use function is_array;
use function json_encode;

/**
 * Class Gateway
 */
class Gateway extends QUI\Utils\Singleton
{
    /**
     * Gateway url param: for the flag if the user is redirected or a system user should be used
     *
     * URL_PARAM_USER_REDIRECTED = 1, URL_PARAM_USER_REDIRECTED = 0
     */
    const URL_PARAM_USER_REDIRECTED = 'UserRedirected';

    /**
     *
     */
    const URL_PARAM_GATEWAY_PAYMENT = 'GatewayPayment';

    /**
     * Internal Order Object
     *
     * @var AbstractOrder|null
     */
    protected ?AbstractOrder $Order = null;

    /**
     * payment gateway status flag
     * 0 = normal gateway
     * 1 = payment request
     *
     * @var int|bool
     */
    protected int|bool $gatewayPayment = false;

    /**
     * Indicates if the Gateway was called as a cancel request
     *
     * @var bool
     */
    protected bool $isCancelRequest = false;

    /**
     * Indicates if the Gateway was called as a success request
     *
     * @var bool
     */
    protected bool $isSuccessRequest = false;

    /**
     * Read the request and look in which step we are
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public function readRequest(): void
    {
        QUI::getEvents()->fireEvent('paymentsGatewayReadRequest', [$this]);

        if ($this->Order !== null) {
            return;
        }

        if (!isset($_REQUEST['orderHash'])) {
            return;
        }

        $Handler = QUI\ERP\Order\Handler::getInstance();

        /* @var $Order QUI\ERP\Order\Order */
        $this->Order = $Handler->getOrderByHash($_REQUEST['orderHash']);

        $Payment = $this->Order->getPayment();

        if (!empty($Payment) && $Payment->getPaymentType()->isGateway()) {
            $this->enableGatewayPayment();
        }

        if (!empty($_REQUEST['canceled'])) {
            $this->isCancelRequest = true;
        }

        if (!empty($_REQUEST['success'])) {
            $this->isSuccessRequest = true;
        }
    }

    /**
     * Set the order id to the gateway
     *
     * @param integer $orderId
     *
     * @throws QUI\Exception
     */
    public function setOrderId(int $orderId): void
    {
        $Handler = QUI\ERP\Order\Handler::getInstance();

        /* @var $Order QUI\ERP\Order\Order */
        try {
            $this->Order = $Handler->get($orderId);
        } catch (QUI\ERP\Order\Exception) {
            try {
                $this->Order = $Handler->getOrderInProcess($orderId);
            } catch (QUI\ERP\Order\Exception $Exception) {
                echo $Exception->getMessage();
                exit;
            }
        }
    }

    /**
     * Set the order to the gateway
     *
     * @param mixed $order - could be order id, order hash, Order or OrderInProcess
     */
    public function setOrder(mixed $order): void
    {
        if ($order instanceof QUI\ERP\Order\OrderInProcess) {
            $this->Order = $order;

            return;
        }

        if ($order instanceof QUI\ERP\Order\Order) {
            $this->Order = $order;

            return;
        }


        $Handler = QUI\ERP\Order\Handler::getInstance();

        try {
            $this->Order = $Handler->getOrderByHash($order);

            return;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            $this->Order = $Handler->get($order);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        try {
            $this->Order = $Handler->getOrderInProcess($order);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * @return OrderInProcess|Order|null
     */
    public function getOrder(): QUI\ERP\Order\OrderInProcess|QUI\ERP\Order\Order|null
    {
        return $this->Order;
    }

    /**
     * Execute the request from the payment provider
     *
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function executeGatewayPayment(): void
    {
        $Order = $this->getOrder();
        $Payment = $Order->getPayment()->getPaymentType();

        try {
            $Payment->executeGatewayPayment($this);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            $Order->addHistory(
                json_encode([
                    'message' => $Exception->getMessage(),
                    'code' => $Exception->getCode()
                ])
            );
        }
    }

    /**
     * Set the gateway to a gateway payment
     * if this flag is active, the gateway thinks that it is executed by a payment
     */
    public function enableGatewayPayment(): void
    {
        $this->gatewayPayment = true;
    }

    /**
     * Set the gateway to a normal gateway request
     * if this flag is deactivate, the gateway thinks that it is executed by a normal request
     */
    public function disableGatewayPayment(): void
    {
        $this->gatewayPayment = false;
    }

    /**
     * @return int|bool
     */
    public function isGatewayPayment(): bool|int
    {
        return $this->gatewayPayment;
    }

    /**
     * Payment API
     */

    /**
     * @param float $amount
     * @param QUI\ERP\Currency\Currency $Currency
     * @param QUI\ERP\Order\AbstractOrder $Order
     * @param QUI\ERP\Accounting\Payments\Api\AbstractPayment $Payment
     * @param array $paymentData
     *
     * @return QUI\ERP\Accounting\Payments\Transactions\Transaction
     *
     * @throws QUI\ERP\Accounting\Payments\Transactions\Exception
     */
    public function purchase(
        float $amount,
        QUI\ERP\Currency\Currency $Currency,
        QUI\ERP\Order\AbstractOrder $Order,
        QUI\ERP\Accounting\Payments\Api\AbstractPayment $Payment,
        array $paymentData = []
    ): QUI\ERP\Accounting\Payments\Transactions\Transaction {
        $paymentComment = QUI::getLocale()->get('quiqqer/payments', 'comment.add.payment', [
            'payment' => $Payment->getTitle(),
            'amount' => $amount,
            'currency' => $Currency->getCode()
        ]);

        $hash = $Order->getUUID();

        $Order->addHistory($paymentComment);

        $Transaction = Transactions::createPaymentTransaction(
            $amount,
            $Currency,
            $hash,
            $Payment->getName(),
            $paymentData
        );

        // refresh, so that the transactions and invoice are also recognized
        try {
            $Order = QUI\ERP\Order\Handler::getInstance()->getOrderByHash($hash);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return $Transaction;
        }

        if ($Order->isPosted()) {
            try {
                $Order->getInvoice()->addHistory($paymentComment);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $Transaction;
    }

    /**
     *
     */
    public function paymentError()
    {
    }

    /**
     * URL Methods
     */

    /**
     * Return the gateway url
     * - you can send params to the gateway with $params
     *
     * @param array $params
     *
     * $params[ Gateway::USER_REDIRECTED ] = 1
     *
     * @return string
     */
    public function getGatewayUrl(array $params = []): string
    {
        $host = $this->getHost();
        $dir = URL_DIR . 'PaymentsGateway'; // new url

        if (!is_array($params)) {
            $params = [];
        }

        if ($this->getOrder()) {
            $params['orderHash'] = $this->getOrder()->getUUID();
        }

        return $host . $dir . '?' . http_build_query($params);
    }

    /**
     * @return string
     */
    public function getSuccessUrl(): string
    {
        return $this->getGatewayUrl([
            'success' => 1,
            'orderHash' => $this->getOrder()->getUUID()
        ]);
    }

    /**
     * Return the url to the specific order
     *
     * @return string
     */
    public function getOrderUrl(): string
    {
        $Project = null;

        try {
            $Project = QUI::getRewrite()->getProject();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        if ($Project === null) {
            try {
                $Project = QUI::getProjectManager()->getStandard();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                return '';
            }
        }

        return QUI\ERP\Order\Utils\Utils::getOrderUrl($Project, $this->getOrder());
    }

    /**
     * @return string
     */
    public function getCancelUrl(): string
    {
        return $this->getGatewayUrl([
            'canceled' => 1,
            'orderHash' => $this->getOrder()->getUUID()
        ]);
    }

    /**
     * @return string
     */
    public function getErrorUrl(): string
    {
        return $this->getGatewayUrl([
            'error' => 1,
            'orderHash' => $this->getOrder()->getHash()
        ]);
    }

    /**
     * This url is for the payment provider to proceed some payments for the order
     */
    public function getPaymentProviderUrl(): string
    {
        return $this->getGatewayUrl([
            Gateway::URL_PARAM_GATEWAY_PAYMENT => 1
        ]);
    }

    /**
     * Return the gateway host
     *
     * @return string
     */
    protected function getHost(): string
    {
        $HOST = HOST;

        if (QUI::conf('globals', 'httpshost')) {
            $HOST = QUI::conf('globals', 'httpshost');
        }

        if (isset($_REQUEST['project']) && str_contains($_REQUEST['project'], '{')) {
            try {
                $Project = QUI::getProjectManager()->decode($_REQUEST['project']);

                if ($Project->getVHost(true, true)) {
                    return $Project->getVHost(true, true);
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if (
            isset($_REQUEST['project'])
            && isset($_REQUEST['lang'])
            && !str_contains($_REQUEST['project'], '{')
        ) {
            try {
                $Project = QUI::getProjectManager()->getProject(
                    $_REQUEST['project'],
                    $_REQUEST['lang']
                );

                if ($Project->getVHost(true, true)) {
                    return $Project->getVHost(true, true);
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            return 'http://' . $_SERVER['HTTP_HOST'];
        }

        try {
            $Project = QUI::getRewrite()->getProject();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }


        // prüfen ob das aktuelle projekt https hat
        if ($Project->getVHost(true, true)) {
            $HOST = $Project->getVHost(true, true);
        }

        return $HOST;
    }

    /**
     * Is the current gateway request a cancel request?
     *
     * @return bool
     */
    public function isCancelRequest()
    {
        return $this->isCancelRequest;
    }

    /**
     * Is the current gateway request a success request?
     *
     * @return bool
     */
    public function isSuccessRequest()
    {
        return $this->isSuccessRequest;
    }
}
