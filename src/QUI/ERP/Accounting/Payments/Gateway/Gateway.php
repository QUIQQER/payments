<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Gateway\Gateway
 */

namespace QUI\ERP\Accounting\Payments\Gateway;

use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Factory as Transactions;

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
     * @var QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess
     */
    protected $Order = null;

    /**
     * payment gateway status flag
     * 0 = normal gateway
     * 1 = payment request
     *
     * @var int
     */
    protected $gatewayPayment = false;

    /**
     * Indicates if the Gateway was called as a cancel request
     *
     * @var bool
     */
    protected $isCancelRequest = false;

    /**
     * Indicates if the Gateway was called as a success request
     *
     * @var bool
     */
    protected $isSuccessRequest = false;

    /**
     * Read the request and look in which step we are
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Exception
     */
    public function readRequest()
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
    public function setOrderId($orderId)
    {
        $Handler = QUI\ERP\Order\Handler::getInstance();

        /* @var $Order QUI\ERP\Order\Order */
        try {
            $this->Order = $Handler->get($orderId);
        } catch (QUI\ERP\Order\Exception $Exception) {
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
    public function setOrder($order)
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
     * @return QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess
     */
    public function getOrder()
    {
        return $this->Order;
    }

    /**
     * Execute the request from the payment provider
     *
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function executeGatewayPayment()
    {
        $Order   = $this->getOrder();
        $Payment = $Order->getPayment()->getPaymentType();

        try {
            $Payment->executeGatewayPayment($this);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            $Order->addHistory(\json_encode([
                'message' => $Exception->getMessage(),
                'code'    => $Exception->getCode()
            ]));
        }
    }

    /**
     * Set the gateway to a gateway payment
     * if this flag is active, the gateway thinks that it is executed by a payment
     */
    public function enableGatewayPayment()
    {
        $this->gatewayPayment = true;
    }

    /**
     * Set the gateway to a normal gateway request
     * if this flag is deactive, the gateway thinks that it is executed by a normal request
     */
    public function disableGatewayPayment()
    {
        $this->gatewayPayment = false;
    }

    /**
     * @return int
     */
    public function isGatewayPayment()
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
        $paymentData = []
    ) {
        $paymentComment = QUI::getLocale()->get('quiqqer/payments', 'comment.add.payment', [
            'payment'  => $Payment->getTitle(),
            'amount'   => $amount,
            'currency' => $Currency->getCode()
        ]);

        $hash = $Order->getHash();

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
                /* @var $Order QUI\ERP\Order\Order */
                $Order->getInvoice()->addHistory($paymentComment);
            } catch (QUI\ERP\Accounting\Invoice\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
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
    public function getGatewayUrl($params = [])
    {
        $host = $this->getHost();
        $dir  = URL_OPT_DIR.'quiqqer/payments/bin/gateway.php';

        if (!\is_array($params)) {
            $params = [];
        }

        if ($this->getOrder()) {
            $params['orderHash'] = $this->getOrder()->getHash();
        }

        return $host.$dir.'?'.\http_build_query($params);
    }

    /**
     * @return string
     */
    public function getSuccessUrl()
    {
        return $this->getGatewayUrl([
            'success'   => 1,
            'orderHash' => $this->getOrder()->getHash()
        ]);
    }

    /**
     * Return the url to the specific order
     *
     * @return string
     */
    public function getOrderUrl()
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
    public function getCancelUrl()
    {
        return $this->getGatewayUrl([
            'canceled'  => 1,
            'orderHash' => $this->getOrder()->getHash()
        ]);
    }

    /**
     * @return string
     */
    public function getErrorUrl()
    {
        return $this->getGatewayUrl([
            'error'     => 1,
            'orderHash' => $this->getOrder()->getHash()
        ]);
    }

    /**
     * This url is for the payment provider to proceed some payments for the order
     */
    public function getPaymentProviderUrl()
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
    protected function getHost()
    {
        $HOST = HOST;

        if (QUI::conf('globals', 'httpshost')) {
            $HOST = QUI::conf('globals', 'httpshost');
        }

        if (isset($_REQUEST['project']) && \strpos($_REQUEST['project'], '{') !== false) {
            try {
                $Project = QUI::getProjectManager()->decode($_REQUEST['project']);

                if ($Project->getVHost(true, true)) {
                    return $Project->getVHost(true, true);
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if (isset($_REQUEST['project'])
            && isset($_REQUEST['lang'])
            && \strpos($_REQUEST['project'], '{') === false) {
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
            return 'http://'.$_SERVER['HTTP_HOST'];
        }

        try {
            $Project = QUI::getRewrite()->getProject();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }


        // prüfen ob das aktuelle projekt https hat
        if ($Project && $Project->getVHost(true, true)) {
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
