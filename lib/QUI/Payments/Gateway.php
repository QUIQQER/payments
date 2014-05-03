<?php

/**
 * This file contains \QUI\Payments\Gateway
 */

namespace QUI\Payments;

use \QUI\Accounting\Accounting as Acc;

/**
 * Gateway for the payments
 *
 * @author www.pcsg.de (Henning Leutz)
 */

class Gateway
{
    /**
     * Internal Order Object
     * @var \QUI\Accounting\Bill|\QUI\Accounting\Order
     */
    static $Order = null;

    /**
     * Accounting hash
     * @var String
     */
    static $HASH = null;

    /**
     * set the gateway global accounting hash
     * @param unknown $HASH
     */
    static function setHash($HASH)
    {
        self::$HASH = $HASH;
    }

    /**
     * Return the gateway url for a payment
     *
     * If you want to open a specific gateway.php from a module
     * set $params['module'] => Payment Name
     *
     * @param Array $param
     * @return String
     */
    static function getGatewayUrl($params=array())
    {
        if ( is_null( self::$HASH ) && isset( $_REQUEST[ 'hash' ] ) ) {
            self::$HASH = $_REQUEST[ 'hash' ];
        }

        $params['hash'] = self::$HASH;

        $gateway = URL_OPT_DIR .'payment/bin/gateway.php?';
        $query   = http_build_query( $params );

        return self::getHost() . $gateway . $query;
    }

    /**
     * Return the url to open the order
     */
    static function getOrderUrl()
    {
        if ( is_null( self::$HASH ) && isset( $_REQUEST[ 'hash' ] ) ) {
            self::$HASH = $_REQUEST[ 'hash' ];
        }

        return self::getHost() .'#order-'. self::$HASH;
    }

    /**
     * Return the gateway host
     *
     * @return String
     */
    static function getHost()
    {
        $HOST = HOST;

        if ( \QUI::conf( 'globals', 'httpshost' ) ) {
            $HOST = \QUI::conf( 'globals', 'httpshost' );
        }

        if ( isset( $_REQUEST['project'] ) && isset( $_REQUEST['lang'] ) )
        {
            try
            {
                $Project = \QUI::getProjectManager()->getProject(
                    $_REQUEST['project'],
                    $_REQUEST['lang']
                );

                if ( $Project->getVHost( true, true ) ) {
                    return $Project->getVHost( true, true );
                }

            } catch ( \QUI\Exception $Exception )
            {

            }
        }

        if ( isset( $_SERVER['HTTP_HOST'] ) ) {
            return 'https://'. $_SERVER['HTTP_HOST'];
        }

        $Project = \QUI::getRewrite()->getProject();

        // prüfen ob das aktuelle projekt https hat
        if ( $Project && $Project->getVHost( true, true ) ) {
            $HOST = $Project->getVHost( true, true );
        }

        return $HOST;
    }

    /**
     * Get an order by hash
     *
     * @param unknown $hash
     * @return AccountingBill|AccountingOrder
     */
    static function getOrder()
    {
        if ( !self::$Order ) {
            self::$Order = Acc::getByHash( self::$HASH );
        }

        return self::$Order;
    }

    /**
     * Add a payment to an Order
     *
     * @param String|Number $amount  - amount
     * @param PaymentModule $Payment - payment
     * @return bool - If the payment was added successfully
     */
    static function addPayment($amount, \QUI\Payments\Payment $Payment)
    {
        if ( $amount <= 0 ) {
            return false;
        }

        try
        {
            $Order = self::getOrder();

        } catch ( \QUI\Exception $Exception )
        {
            self::setError( $Exception->getMessage() );
            return false;
        }

        try
        {
            $Order->addPayment(
                \QUI\Utils\String::parseFloat( $amount ),
                date( 'c' ),
                $Payment->getAttribute( 'name' )
            );

            self::addComment(
                'Bezahlung '. $amount .' mit '. $Payment->getAttribute( 'name' )
            );

        } catch ( \QUI\Exception $Exception )
        {
            $Order->addComment( $Exception->getMessage() );
            return false;
        }

        try
        {
            $Order->createBill();

        } catch ( \QUI\Accounting\Exception $Exception )
        {
            $Order->addComment( $Exception->getMessage() );
        }

        return true;
    }

    /**
     * Nachricht der Bestellung / Rechnung hinzufügen
     *
     * @param unknown $message
     */
    static function addComment($message)
    {
        try
        {
            $Order = self::getOrder();
            $Order->addComment( $message );

        } catch ( \QUI\Exception $Exception )
        {
            self::setError( $Exception->getMessage() );
        }
    }

    /**
     * Zahlungsinformationen der Bestellung / Rechnung hinzufügen
     *
     * @param String $key
     * @param String|Array $val
     */
    static function setPaymentData($key, $val)
    {
        try
        {
            $Order = self::getOrder();
            $Order->setPaymentData( $key, $val );

        } catch ( \QUI\Exception $Exception )
        {
            self::setError( $Exception->getMessage() );
        }
    }

    /**
     * Zahlungsinformationen der Bestellung / Rechnung bekommen
     *
     * @param String $key
     */
    static function getPaymentData($key)
    {
        try
        {
            $Order = self::getOrder();
            return $Order->getPaymentData( $key );

        } catch ( \QUI\Exception $Exception )
        {
            self::setError( $Exception->getMessage() );
            return false;
        }
    }

    /**
     *
     * @param unknown $hash
     */
    static function storno($hash)
    {
        try
        {
            $Order = self::getOrder();

            // @todo storno


            $Order->addComment(
                'Storno der Bestellung'
            );

        } catch ( \QUI\Exception $Exception )
        {
            self::setError( 'Storno der Bestellung' );
            self::setError( $Exception->getMessage() );
        }
    }

    /**
     * Setzt ein Error log für das payment über das gateway
     * @param unknown $txt
     */
    static function setError($txt)
    {
        \QUI\System\Log::addError( $txt );
    }
}
