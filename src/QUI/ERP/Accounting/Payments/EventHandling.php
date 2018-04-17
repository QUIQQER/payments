<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\EventHandling
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\Package\Package;
use QUI\ERP\Accounting\Payments\Methods;
use QUI\ERP\Accounting\Payments\Types\Factory;

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
            $Settings->save();

            return;
        }

        $settings = json_decode($params['payments']['paymentsJson'], true);

        foreach ($settings as $payment => $status) {
            try {
                $Payments->getPayment($payment);
                $Settings->set('payments', $payment, $status ? 1 : 0);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }

        $Settings->save();
    }

    /**
     * @param Package $Package
     * @throws QUI\Exception
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() != 'quiqqer/products') {
            return;
        }

        // create the standard payment types
        $Locale   = QUI::getLocale();
        $Factory  = new Factory();
        $children = $Factory->getChildren();

        $existingTypes = array_map(function ($PaymentType) {
            /* @var $PaymentType QUI\ERP\Accounting\Payments\Types\Payment */
            return $PaymentType->getAttribute('payment_type');
        }, $children);

        $existingTypes = array_flip($existingTypes);


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
}
