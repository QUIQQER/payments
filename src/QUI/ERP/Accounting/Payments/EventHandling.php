<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\EventHandling
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\Package\Package;
use QUI\ERP\Accounting\Payments\Methods;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\OrderInterface;

/**
 * Class EventHandling
 *
 * @package QUI\ERP\Accounting\Payments
 */
class EventHandling
{
    /**
     * @param Package $Package
     * @param array $params
     */
    public static function onPackageConfigSave(Package $Package, $params)
    {
        if ($Package->getName() !== 'quiqqer/payments') {
            return;
        }

        if (!isset($params['payments'])
            || !isset($params['payments']['paymentsJson'])
        ) {
            return;
        }

        $Settings = Settings::getInstance();
        $Payments = Payments::getInstance();

        if (empty($params['payments']['paymentsJson'])) {
            $Settings->removeSection('payments');

            try {
                $Settings->save();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }

            return;
        }

        $settings = \json_decode($params['payments']['paymentsJson'], true);

        foreach ($settings as $payment => $status) {
            try {
                $Payments->getPayment($payment);
                $Settings->set('payments', $payment, $status ? 1 : 0);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }

        try {
            $Settings->save();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Package $Package
     * @throws QUI\Exception
     */
    public static function onPackageInstall(Package $Package)
    {
        if ($Package->getName() != 'quiqqer/products') {
            return;
        }

        // create the standard payment types
        $Locale   = QUI::getLocale();
        $Factory  = new Factory();
        $children = $Factory->getChildren();

        $existingTypes = \array_map(function ($PaymentType) {
            /* @var $PaymentType QUI\ERP\Accounting\Payments\Types\Payment */
            return $PaymentType->getAttribute('payment_type');
        }, $children);

        $existingTypes = \array_flip($existingTypes);


        if (!isset($existingTypes[Methods\AdvancePayment\Payment::class])) {
            $Payment = $Factory->createChild([
                'payment_type' => Methods\AdvancePayment\Payment::class
            ]);

            $Payment->setTitle([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.advanced.payment.title'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.advanced.payment.title')
            ]);

            $Payment->setDescription([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.advanced.payment.description'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.advanced.payment.description')
            ]);

            $Payment->setWorkingTitle([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.advanced.payment.workingTitle'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.advanced.payment.workingTitle')
            ]);

            $Payment->activate();
        }

        if (!isset($existingTypes[Methods\Cash\Payment::class])) {
            $Payment = $Factory->createChild([
                'payment_type' => Methods\Cash\Payment::class
            ]);

            $Payment->setTitle([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.cash.title'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.cash.title')
            ]);

            $Payment->setDescription([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.cash.description'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.cash.description')
            ]);

            $Payment->setWorkingTitle([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.cash.workingTitle'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.cash.workingTitle')
            ]);

            $Payment->activate();
        }

        if (!isset($existingTypes[Methods\Invoice\Payment::class])) {
            $Payment = $Factory->createChild([
                'payment_type' => Methods\Invoice\Payment::class
            ]);

            $Payment->setTitle([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.invoice.title'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.invoice.title')
            ]);

            $Payment->setDescription([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.invoice.description'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.invoice.description')
            ]);

            $Payment->setWorkingTitle([
                'de' => $Locale->getByLang('de', 'quiqqer/payments', 'payment.invoice.workingTitle'),
                'en' => $Locale->getByLang('en', 'quiqqer/payments', 'payment.invoice.workingTitle')
            ]);

            $Payment->activate();
        }
    }

    /**
     * quiqqer/payments: onPaymentsCanUsedInOrder
     *
     * Check if an Order contains a plan product and if a payment method is allowed to be used
     * in this case.
     *
     * @param Payment $Payment
     * @param OrderInterface $Order
     * @throws QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed
     */
    public static function onPaymentsCanUsedInOrder(Payment $Payment, OrderInterface $Order)
    {
        /**
         * @todo In the future there may be other packages that check if payment types
         * can be used for an Order. Currently this is only quiqqer/erp-plans.
         *
         * If quiqqer/erp-plans is installed it handles this process of deciding
         * which payment type to allow
         */
        if (QUI::getPackageManager()->isInstalled('quiqqer/erp-plans')) {
            return;
        }

        try {
            $PaymentType = $Payment->getPaymentType();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        /**
         * A payment type that can ONLY handle recurring payments is not suited
         * for any order in a system where quiqqer/erp-plans is not installed.
         */
        if ($PaymentType->supportsRecurringPaymentsOnly()) {
            throw new QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed();
        }
    }
}
