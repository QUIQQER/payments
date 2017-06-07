<?php

/**
 * This class contains \QUI\ERP\Accounting\Payments\Handler
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Types\Payment;

/**
 * Payments
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Payments extends QUI\Utils\Singleton
{
    protected $payments = array();

    /**
     * constructor
     */
    public function __construct()
    {

//        $cachePayments = 'package/quiqqer/payments/payments';
//        $cacheProvider = 'package/quiqqer/payments/provider';
//
//        try {
//            $providerPayments = QUI\Cache\Manager::get($cachePayments);
//
//            foreach ($providerPayments as $providerPayment) {
//                $Payment = new $providerPayment();
//
//                if ($Payment instanceof AbstractPayment) {
//                    $this->payments[$Payment->getName()] = $Payment;
//                }
//            }
//            return;
//        } catch (QUI\Exception $Exception) {
//        }
//
//        $packages = array_map(function ($package) {
//            return $package['name'];
//        }, QUI::getPackageManager()->getInstalled());
//
//        $payments = array();
//
//        try {
//            $providers = QUI\Cache\Manager::get($cacheProvider);
//        } catch (QUI\Cache\Exception $Exception) {
//            $providers = array();
//
//            foreach ($packages as $package) {
//                try {
//                    $Package = QUI::getPackage($package);
//
//                    if ($Package->isQuiqqerPackage()) {
//                        $providers = array_merge($providers, $Package->getProvider('payment'));
//                    }
//                } catch (QUI\Exception $Exception) {
//                }
//            }
//        }
//
//        // filter provider
//        foreach ($providers as $provider) {
//            if (!class_exists($provider)) {
//                continue;
//            }
//
//            $Provider = new $provider();
//
//            if (!($Provider instanceof AbstractPaymentProvider)) {
//                continue;
//            }
//
//            $providerPayments = $Provider->getPaymentMethods();
//
//            foreach ($providerPayments as $providerPayment) {
//                if (!class_exists($providerPayment)) {
//                    continue;
//                }
//
//                $Payment = new $providerPayment();
//
//                if ($Payment instanceof AbstractPayment) {
//                    $payments[$Payment->getName()] = $Payment;
//                }
//            }
//        }
//
//        $this->payments = $payments;
//
//        QUI\Cache\Manager::set($cacheProvider, $this->payments);
    }

    /**
     * Return a payment
     *
     * @param int|string $paymentId - ID of the payment type
     * @return Payment
     *
     * @throws Exception
     */
    public function getPayment($paymentId)
    {
        /* @var $Payment Payment */
        try {
            $Payment = Factory::getInstance()->getChild($paymentId);
            return $Payment;
        } catch (QUI\Exception $exception) {
            throw new Exception(array(
                'quiqqer/payments',
                'exception.payment.not.found'
            ));
        }
    }

    /**
     * Return all active payments
     *
     * @return array
     */
    public function getPayments()
    {
        return Factory::getInstance()->getChildren();
    }

    /**
     * Return all payments for the user
     *
     * @param \QUI\Interfaces\Users\User|null $User - optional
     * @return array
     */
    public function getUserPayments($User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        $payments = array_filter($this->getPayments(), function ($Payment) use ($User) {
            /* @var $Payment Payment */
            return $Payment->canUsedBy($User);
        });

        return $payments;
    }
}
