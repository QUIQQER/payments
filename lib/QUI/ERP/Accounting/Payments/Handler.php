<?php

/**
 * This class contains \QUI\ERP\Accounting\Payments\Handler
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;

/**
 * Payment handler
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Handler
{
    const SUCCESS_TYPE_PAY = 1;
    const SUCCESS_TYPE_BILL = 2;

    protected $_payments = array();

    /**
     * constructor
     */
    public function __construct()
    {
        // cache?
        try {
            $this->_payments = QUI\Cache\Manager::get(
                'package/quiqqer/payments/list'
            );

            return;
        } catch (QUI\Exception $Exception) {
        }

        $packages = \QUI::getPackageManager()->getInstalled(array(
            'type' => 'quiqqer-payment'
        ));

        $list = array();

        foreach ($packages as $package) {
            $name     = $package['name'];
            $xml_file = OPT_DIR . $name . '/payments.xml';

            $Dom      = QUI\Utils\Text\XML::getDomFromXml($xml_file);
            $payments = $Dom->getElementsByTagName('payments');

            for ($i = 0, $len = $payments->length; $i < $len; $i++) {
                $Payment     = $payments->item($i);
                $paymentName = $Payment->getAttribute('name');

                $list[$name . ':' . $paymentName] = array(
                    'name' => $paymentName,
                    'exec' => $Payment->getAttribute('exec')
                );
            }
        }

        $this->_payments = $list;

        QUI\Cache\Manager::set(
            'package/quiqqer/payments/list',
            $this->_payments
        );
    }

    /**
     * Return the config for the payment list
     *
     * @return \QUI\Config
     */
    public function getPaymentConfig()
    {
        if (!file_exists(CMS_DIR . 'etc/payments/list.ini')) {
            file_put_contents(CMS_DIR . 'etc/payments/list.ini', '');
        }

        $Config = new QUI\Config(CMS_DIR . 'etc/payments/list.ini');

        return $Config;
    }

    /**
     * Return a payment, if the payment is active
     *
     * @param string $payment
     * @return \QUI\ERP\Accounting\Payments\Payment|false
     */
    public function get($payment)
    {
        $payments = $this->getPayments();

        if (isset($payments[$payment])) {
            return false;
        }

        $exec = $payments[$payment]['exec'];

        if (!is_callable($exec)) {
            return false;
        }

        return new $exec();
    }

    /**
     * Return all active payments
     *
     * @return array
     */
    public function getPayments()
    {
        $result   = array();
        $payments = $this->getAllPayments();

        $Config = $this->getPaymentConfig();
        $config = $Config->toArray();

        foreach ($payments as $payment => $params) {
            if (isset($config[$payment]) && $config[$payment] == 1) {
                $result[$payment] = $params;
            }
        }

        return $result;
    }

    /**
     * Return all payments
     *
     * @return array
     */
    public function getAllPayments()
    {
        return $this->_payments;
    }
}
