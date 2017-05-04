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
class Handler extends QUI\Utils\Singleton
{
    protected $payments = array();

    /**
     * constructor
     */
    public function __construct()
    {
        $cachePayments = 'package/quiqqer/payments/payments';
        $cacheProvider = 'package/quiqqer/payments/provider';

        try {
            $providerPayments = QUI\Cache\Manager::get($cachePayments);

            foreach ($providerPayments as $providerPayment) {
                $Payment = new $providerPayment();

                if ($Payment instanceof AbstractPayment) {
                    $this->payments[$Payment->getName()] = $Payment;
                }
            }
            return;
        } catch (QUI\Exception $Exception) {
        }

        $packages = QUI::getPackageManager()->getInstalled();
        $payments = array();

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception $Exception) {
            $providers = array();

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = array_merge($providers, $Package->getProvider('erp'));
                    }
                } catch (QUI\Exception $Exception) {
                }
            }
        }


        // filter provider
        $providers = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($providers)
        );

        foreach ($providers as $entry) {
            if (!class_exists($entry)) {
                continue;
            }

            $Provider = new $entry();

            if (!($Provider instanceof Provider)) {
                continue;
            }

            $providerPayments = $Provider->getPayments();

            foreach ($providerPayments as $providerPayment) {
                $Payment = new $providerPayment();

                if ($Payment instanceof AbstractPayment) {
                    $this->payments[$Payment->getName()] = $Payment;
                }
            }
        }

        $this->payments = $payments;

        QUI\Cache\Manager::set($cacheProvider, $this->payments);
    }

    /**
     * Return a payment, if the payment is active
     *
     * @param string $payment
     * @return \QUI\ERP\Accounting\Payments\AbstractPayment
     *
     * @throws Exception
     */
    public function get($payment)
    {
        if (!isset($this->payments[$payment])) {
            throw new Exception(array(
                'quiqqer/payments',
                'exception.payment.not.found'
            ));
        }

        return $this->payments[$payment];
    }

    /**
     * Return all active payments
     *
     * @return array
     */
    public function getPayments()
    {
        $result   = array();
        $payments = $this->getAvailablePayments();

        $Config = QUI::getPackage('quiqqer/payments')->getConfig();
        $config = $Config->toArray();

        /* @var $Payment AbstractPayment */
        foreach ($payments as $payment => $Payment) {
            $name = $Payment->getName();

            if (isset($config[$name]) && $config[$name] == 1) {
                $result[$name] = $Payment;
            }
        }

        return $result;
    }

    /**
     * Return all available payments
     *
     * @return array
     */
    public function getAvailablePayments()
    {
        return $this->payments;
    }
}
