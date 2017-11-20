<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Gateway\Gateway
 */

namespace QUI\ERP\Accounting\Payments\Gateway;

use QUI;

/**
 * Class Gateway
 */
class Gateway extends QUI\Utils\Singleton
{
    /**
     * Internal Order Object
     *
     * @var QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess
     */
    protected $Order = null;

    /**
     * Read the request and look in which step we are
     */
    public function readRequest()
    {
        if (!isset($_REQUEST['orderHash'])) {
            return;
        }

        $Handler = QUI\ERP\Order\Handler::getInstance();

        /* @var $Order QUI\ERP\Order\Order */
        try {
            $this->Order = $Handler->get($_REQUEST['orderHash']);
        } catch (QUI\ERP\Order\Exception $Exception) {
            try {
                $this->Order = $Handler->getOrderInProcess($_REQUEST['orderHash']);
            } catch (QUI\ERP\Order\Exception $Exception) {
                echo $Exception->getMessage();
                exit;
            }
        }
    }

    /**
     * Set the order id to the gateway
     *
     * @param integer $orderId
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
     * @return QUI\ERP\Order\Order|QUI\ERP\Order\OrderInProcess
     */
    public function getOrder()
    {
        return $this->Order;
    }

    /**
     * URL Methods
     */

    /**
     * Return the gateway url
     * - you can send params to the gateway with $params
     *
     * @param array $params
     * @return string
     */
    public function getGatewayUrl($params = array())
    {
        $host = $this->getHost();
        $dir  = URL_OPT_DIR.'quiqqer/payments/bin/gateway.php';

        if (empty($params)) {
            $url = $dir;
        } else {
            $url = $dir.'?'.http_build_query($params);
        }

        return $host.$url;
    }

    /**
     * @return string
     */
    public function getSuccessUrl()
    {
        return $this->getGatewayUrl(array(
            'success'   => 1,
            'orderHash' => $this->getOrder()->getHash()
        ));
    }

    /**
     * Return the url to the specific order
     *
     * @return string
     */
    public function getOrderUrl()
    {
        $url = rtrim($this->getHost().URL_DIR, '/');

        return $url.'/Bestellungen?order='.$this->getOrder()->getHash();
    }

    /**
     * @return string
     */
    public function getCancelUrl()
    {
        return $this->getGatewayUrl(array(
            'canceled'  => 1,
            'orderHash' => $this->getOrder()->getHash()
        ));
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

        if (isset($_REQUEST['project']) && isset($_REQUEST['lang'])) {
            try {
                $Project = QUI::getProjectManager()->getProject(
                    $_REQUEST['project'],
                    $_REQUEST['lang']
                );

                if ($Project->getVHost(true, true)) {
                    return $Project->getVHost(true, true);
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            return 'https://'.$_SERVER['HTTP_HOST'];
        }

        $Project = \QUI::getRewrite()->getProject();

        // prüfen ob das aktuelle projekt https hat
        if ($Project && $Project->getVHost(true, true)) {
            $HOST = $Project->getVHost(true, true);
        }

        return $HOST;
    }
}
