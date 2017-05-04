<?php

/**
 * This class contains \QUI\ERP\Accounting\Payments\Handler
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Api\AbstractPaymentProvider;

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

        $packages = array_map(function ($package) {
            return $package['name'];
        }, QUI::getPackageManager()->getInstalled());

        $payments = array();

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception $Exception) {
            $providers = array();

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = array_merge($providers, $Package->getProvider('payment'));
                    }
                } catch (QUI\Exception $Exception) {
                }
            }
        }

        // filter provider
        foreach ($providers as $provider) {
            if (!class_exists($provider)) {
                continue;
            }

            $Provider = new $provider();

            if (!($Provider instanceof AbstractPaymentProvider)) {
                continue;
            }

            $providerPayments = $Provider->getPayments();

            foreach ($providerPayments as $providerPayment) {
                if (!class_exists($providerPayment)) {
                    continue;
                }

                $Payment = new $providerPayment();

                if ($Payment instanceof AbstractPayment) {
                    $payments[$Payment->getName()] = $Payment;
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
     * @return AbstractPayment
     *
     * @throws Exception
     */
    public function getPayment($payment)
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
        $Config   = QUI::getPackage('quiqqer/payments')->getConfig();
        $payments = $Config->getSection('payments');

        foreach ($payments as $payment => $status) {
            if ((int)$status !== 1) {
                continue;
            }

            try {
                $result[$payment] = $this->getPayment($payment);
            } catch (Exception $Exception) {
                QUI\System\Log::addNotice($Exception->getMessage());
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
