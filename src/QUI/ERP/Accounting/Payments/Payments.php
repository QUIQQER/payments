<?php

/**
 * This class contains \QUI\ERP\Accounting\Payments\Handler
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Api\AbstractPaymentProvider;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\Exception;
use QUI\Interfaces\Users\User;

use function array_filter;
use function array_merge;
use function class_exists;
use function trim;

/**
 * Payments
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Payments extends QUI\Utils\Singleton
{
    /**
     * @var array<string, AbstractPayment>
     */
    protected array $payments = [];

    /**
     * Return all available payment provider
     *
     * @return list<AbstractPaymentProvider>
     */
    public function getPaymentProviders(): array
    {
        $cacheProvider = 'package/quiqqer/payments/provider';

        try {
            $providers = QUI\Cache\Manager::get($cacheProvider);
        } catch (QUI\Cache\Exception) {
            $packages = array_map(function ($package) {
                return $package['name'];
            }, QUI::getPackageManager()->getInstalled());

            $providers = [];

            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package);

                    if ($Package->isQuiqqerPackage()) {
                        $providers = array_merge($providers, $Package->getProvider('payment'));
                    }
                } catch (QUI\Exception) {
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
     * @return AbstractPayment[]
     */
    public function getPaymentTypes(): array
    {
        $payments = [];
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
     * @param string $paymentHash
     * @return AbstractPayment
     * @throws Exception
     */
    public function getPaymentType($paymentHash): AbstractPayment
    {
        $types = $this->getPaymentTypes();

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
    public function getPayment(int | string $paymentId): Methods\Free\PaymentType | Payment
    {
        if ((int)$paymentId == Methods\Free\Payment::ID) {
            return new Methods\Free\PaymentType(Methods\Free\Payment::ID, new Factory());
        }

        /* @var $Payment Payment */
        try {
            return Factory::getInstance()->getChild((int)$paymentId);
        } catch (QUI\Exception) {
            throw new Exception([
                'quiqqer/payments',
                'exception.payment.not.found'
            ]);
        }
    }

    /**
     * Return all active payments
     *
     * @param array<string, mixed> $queryParams
     * @return list<Payment>
     */
    public function getPayments(array $queryParams = []): array
    {
        if (!isset($queryParams['order'])) {
            $queryParams['order'] = 'priority ASC';
        }

        try {
            $children = Factory::getInstance()->getChildren($queryParams);

            return array_values(array_filter(
                $children,
                static fn(QUI\CRUD\Child $Child): bool => $Child instanceof Payment
            ));
        } catch (QUI\Exception) {
            return [];
        }
    }

    /**
     * Return all payments for the user
     *
     * @param User|null $User - optional
     * @return list<Payment>
     */
    public function getUserPayments(null | User $User = null): array
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        return array_values(array_filter($this->getPayments(), function ($Payment) use ($User) {
            /* @var $Payment Payment */
            return $Payment->canUsedBy($User);
        }));
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        try {
            $Project = QUI::getRewrite()->getProject();
        } catch (QUI\Exception) {
            try {
                $Project = QUI::getProjectManager()->getStandard();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                return '';
            }
        }

        if ($Project === null) {
            return '';
        }

        $host = $Project->getVHost(true, true);

        if (!is_string($host)) {
            return '';
        }

        return trim($host, '/');
    }
}
