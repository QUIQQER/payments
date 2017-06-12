<?php

/**
 * This class contains \QUI\ERP\Accounting\Payments\Handler
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Accounting\Payments\Api\AbstractPaymentProvider;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;

/**
 * Payments
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Payments extends QUI\Utils\Singleton
{
    protected $payments = array();

    /**
     * Return all available payment provider
     *
     * @return array
     */
    public function getPaymentProviders()
    {
        $cacheProvider = 'package/quiqqer/payments/provider';

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception $Exception) {
            $packages = array_map(function ($package) {
                return $package['name'];
            }, QUI::getPackageManager()->getInstalled());

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

            QUI\Cache\Manager::set($cacheProvider, $providers);
        }

        // filter provider
        $result = array();

        foreach ($providers as $provider) {
            if (!class_exists($provider)) {
                continue;
            }

            $Provider = new $provider();

            if (!($Provider instanceof AbstractPaymentProvider)) {
                continue;
            }

            $result[] = $Provider;
        }

        return $result;
    }

    /**
     * Return all available payment methods
     *
     * @return array
     */
    public function getPaymentTypes()
    {
        $payments  = array();
        $providers = $this->getPaymentProviders();

        foreach ($providers as $Provider) {
            $providerPayments = $Provider->getPaymentTypes();

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

        return $payments;
    }

    /**
     * @param $paymentHash
     * @return AbstractPayment
     * @throws Exception
     */
    public function getPaymentType($paymentHash)
    {
        $types = $this->getPaymentTypes();

        /* @var $Payment AbstractPayment */
        foreach ($types as $Payment) {
            if ($Payment->getName() === $paymentHash) {
                return $Payment;
            }
        }

        throw new Exception(array(
            'quiqqer/payments',
            'exception.payment.type.not.found',
            array('paymentType' => $paymentHash)
        ));
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
     * @param array $queryParams
     * @return array
     */
    public function getPayments($queryParams = array())
    {
        if (!isset($queryParams['order'])) {
            $queryParams['order'] = 'priority ASC';
        }

        return Factory::getInstance()->getChildren($queryParams);
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
