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
    /**
     * @var array
     */
    protected $payments = [];

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

            $providers = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = \array_merge($providers, $Package->getProvider('payment'));
                    }
                } catch (QUI\Exception $Exception) {
                }
            }

            try {
                QUI\Cache\Manager::set($cacheProvider, $providers);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // filter provider
        $result = [];

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
        $payments  = [];
        $providers = $this->getPaymentProviders();

        foreach ($providers as $Provider) {
            $providerPayments = $Provider->getPaymentTypes();

            foreach ($providerPayments as $providerPayment) {
                if (!\class_exists($providerPayment)) {
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

        throw new Exception([
            'quiqqer/payments',
            'exception.payment.type.not.found',
            ['paymentType' => $paymentHash]
        ]);
    }

    /**
     * Return a payment
     *
     * @param int|string $paymentId - ID of the payment type
     * @return Payment|Methods\Free\PaymentType
     *
     * @throws Exception
     */
    public function getPayment($paymentId)
    {
        if ((int)$paymentId == Methods\Free\Payment::ID) {
            return new Methods\Free\PaymentType(Methods\Free\Payment::ID, new Factory());
        }

        /* @var $Payment Payment */
        try {
            return Factory::getInstance()->getChild($paymentId);
        } catch (QUI\Exception $Exception) {
            throw new Exception([
                'quiqqer/payments',
                'exception.payment.not.found'
            ]);
        }
    }

    /**
     * Return all active payments
     *
     * @param array $queryParams
     * @return array
     */
    public function getPayments($queryParams = [])
    {
        if (!isset($queryParams['order'])) {
            $queryParams['order'] = 'priority ASC';
        }

        try {
            return Factory::getInstance()->getChildren($queryParams);
        } catch (QUI\Exception $Exception) {
            return [];
        }
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

        $payments = \array_filter($this->getPayments(), function ($Payment) use ($User) {
            /* @var $Payment Payment */
            return $Payment->canUsedBy($User);
        });

        return $payments;
    }

    /**
     * @return bool|string
     */
    public function getHost()
    {
        try {
            $Project = QUI::getRewrite()->getProject();
        } catch (QUI\Exception $Exception) {
            try {
                $Project = QUI::getProjectManager()->getStandard();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                return '';
            }
        }

        $host = $Project->getVHost(true, true);
        $host = \trim($host, '/');

        return $host;
    }
}
