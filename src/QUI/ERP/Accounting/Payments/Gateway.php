<?php

/**
 * This file contains \QUI\ERP\Accounting\Payments\Gateway
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Order\Order;

/**
 * Gateway for the payments
 *
 * @author www.pcsg.de (Henning Leutz)
 * @deprecated
 */
class Gateway
{
    /**
     * Internal Order Object
     * @var InvoiceTemporary|Invoice|Order
     */
    protected static $Order = null;

    /**
     * Accounting hash
     *
     * @var string
     */
    protected static $HASH = null;

    /**
     * set the gateway global accounting hash
     *
     * @param string $HASH
     */
    protected static function setHash($HASH)
    {
        self::$HASH = $HASH;
    }

    /**
     * Return the gateway url for a payment
     *
     * If you want to open a specific gateway.php from a module
     * set $params['module'] => Payment Name
     *
     * @param array $params
     * @return string
     */
    public static function getGatewayUrl($params = array())
    {
        if (is_null(self::$HASH) && isset($_REQUEST['hash'])) {
            self::$HASH = $_REQUEST['hash'];
        }

        $params['hash'] = self::$HASH;

        $gateway = URL_OPT_DIR . 'payment/bin/gateway.php?';
        $query   = http_build_query($params);

        return self::getHost() . $gateway . $query;
    }

    /**
     * Return the url to open the order
     */
    public static function getOrderUrl()
    {
        if (is_null(self::$HASH) && isset($_REQUEST['hash'])) {
            self::$HASH = $_REQUEST['hash'];
        }

        return self::getHost() . '#order-' . self::$HASH;
    }

    /**
     * Return the gateway host
     *
     * @return string
     */
    public static function getHost()
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
            return 'https://' . $_SERVER['HTTP_HOST'];
        }

        $Project = \QUI::getRewrite()->getProject();

        // prüfen ob das aktuelle projekt https hat
        if ($Project && $Project->getVHost(true, true)) {
            $HOST = $Project->getVHost(true, true);
        }

        return $HOST;
    }

    /**
     * Return the order
     *
     * @return InvoiceTemporary|Invoice|Order
     */
    public static function getOrder()
    {
        if (!self::$Order) {
            self::$Order = Acc::getByHash(self::$HASH);
        }

        return self::$Order;
    }

    /**
     * Add a payment to an Order
     *
     * @param string|number $amount - amount
     * @param Payment $Payment - payment
     * @return bool - If the payment was added successfully
     */
    public static function addPayment($amount, Payment $Payment)
    {
        if ($amount <= 0) {
            return false;
        }

        try {
            $Order = self::getOrder();
        } catch (\QUI\Exception $Exception) {
            self::setError($Exception->getMessage());
            return false;
        }

        try {
            $Order->addPayment(
                QUI\Utils\StringHelper::parseFloat($amount),
                date('c'),
                $Payment->getAttribute('name')
            );

            self::addComment(
                'Bezahlung ' . $amount . ' mit ' . $Payment->getAttribute('name')
            );
        } catch (QUI\Exception $Exception) {
            $Order->addComment($Exception->getMessage());
            return false;
        }

        try {
            $Order->createBill();
        } catch (Exception $Exception) {
            $Order->addComment($Exception->getMessage());
        }

        return true;
    }

    /**
     * Nachricht der Bestellung / Rechnung hinzufügen
     *
     * @param string $message
     */
    public static function addComment($message)
    {
        try {
            $Order = self::getOrder();
            $Order->addComment($message);
        } catch (QUI\Exception $Exception) {
            self::setError($Exception->getMessage());
        }
    }

    /**
     * Zahlungsinformationen der Bestellung / Rechnung hinzufügen
     *
     * @param string $key
     * @param string|array $val
     */
    public static function setPaymentData($key, $val)
    {
        try {
            $Order = self::getOrder();
            $Order->setPaymentData($key, $val);
        } catch (QUI\Exception $Exception) {
            self::setError($Exception->getMessage());
        }
    }

    /**
     * Zahlungsinformationen der Bestellung / Rechnung bekommen
     *
     * @param string $key
     */
    public static function getPaymentData($key)
    {
        try {
            $Order = self::getOrder();
            return $Order->getPaymentData($key);
        } catch (QUI\Exception $Exception) {
            self::setError($Exception->getMessage());
            return false;
        }
    }

    /**
     * @param string $hash
     */
    public static function storno($hash)
    {
        try {
            $Order = self::getOrder();

            // @todo storno

            $Order->addComment(
                'Storno der Bestellung'
            );
        } catch (QUI\Exception $Exception) {
            self::setError('Storno der Bestellung');
            self::setError($Exception->getMessage());
        }
    }

    /**
     * Setzt ein Error log für das payment über das gateway
     *
     * @param string $text
     */
    public static function setError($text)
    {
        QUI\System\Log::addError($text);
    }
}
