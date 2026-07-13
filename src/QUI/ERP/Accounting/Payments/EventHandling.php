<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\EventHandling
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\OrderInterface;
use QUI\Package\Package;

use function array_flip;
use function array_map;
use function json_decode;
use function method_exists;
use function is_string;

/**
 * Class EventHandling
 *
 * @package QUI\ERP\Accounting\Payments
 */
class EventHandling
{
    /**
     * @param Package $Package
     * @param array<string, mixed> $params
     */
    public static function onPackageConfigSave(Package $Package, array $params): void
    {
        if ($Package->getName() !== 'quiqqer/payments') {
            return;
        }

        if (!isset($params['payments']) || !isset($params['payments']['paymentsJson'])) {
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

        $settings = json_decode($params['payments']['paymentsJson'], true);

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
     * Called as an event when an error code/header is shown/returned
     *
     * @param int|string $code
     * @param string $url
     */
    public static function onErrorHeaderShow($code, $url): void
    {
        if ($code !== 404) {
            return;
        }

        if (!isset($_REQUEST['_url'])) {
            return;
        }

        if ($_REQUEST['_url'] !== 'PaymentsGateway') {
            return;
        }

        require_once OPT_DIR . 'quiqqer/payments/bin/gateway.php';
    }

    /**
     * @param Package $Package
     * @throws QUI\Exception
     */
    public static function onPackageInstallAfter(Package $Package): void
    {
        if ($Package->getName() != 'quiqqer/payments') {
            return;
        }

        // create the standard payment types
        $Locale = QUI::getLocale();
        $Factory = new Factory();
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
                'de' => self::getLocaleText($Locale, 'de', 'payment.advanced.payment.title'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.advanced.payment.title')
            ]);

            $Payment->setDescription([
                'de' => self::getLocaleText($Locale, 'de', 'payment.advanced.payment.description'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.advanced.payment.description')
            ]);

            $Payment->setWorkingTitle([
                'de' => self::getLocaleText($Locale, 'de', 'payment.advanced.payment.workingTitle'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.advanced.payment.workingTitle')
            ]);

            $Payment->activate();
        }

        if (!isset($existingTypes[Methods\Cash\Payment::class])) {
            $Payment = $Factory->createChild([
                'payment_type' => Methods\Cash\Payment::class
            ]);

            $Payment->setTitle([
                'de' => self::getLocaleText($Locale, 'de', 'payment.cash.title'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.cash.title')
            ]);

            $Payment->setDescription([
                'de' => self::getLocaleText($Locale, 'de', 'payment.cash.description'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.cash.description')
            ]);

            $Payment->setWorkingTitle([
                'de' => self::getLocaleText($Locale, 'de', 'payment.cash.workingTitle'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.cash.workingTitle')
            ]);

            $Payment->activate();
        }

        if (!isset($existingTypes[Methods\Invoice\Payment::class])) {
            $Payment = $Factory->createChild([
                'payment_type' => Methods\Invoice\Payment::class
            ]);

            $Payment->setTitle([
                'de' => self::getLocaleText($Locale, 'de', 'payment.invoice.title'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.invoice.title')
            ]);

            $Payment->setDescription([
                'de' => self::getLocaleText($Locale, 'de', 'payment.invoice.description'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.invoice.description')
            ]);

            $Payment->setWorkingTitle([
                'de' => self::getLocaleText($Locale, 'de', 'payment.invoice.workingTitle'),
                'en' => self::getLocaleText($Locale, 'en', 'payment.invoice.workingTitle')
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
    public static function onPaymentsCanUsedInOrder(Payment $Payment, OrderInterface $Order): void
    {
        /**
         * @todo In the future there may be other packages that check if payment types
         * can be used for an Order. However, this is only quiqqer/erp-plans.
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

    /**
     * @param QUI\ERP\Order\Basket\Basket|QUI\ERP\Order\Basket\BasketOrder $Basket
     */
    public static function onQuiqqerOrderBasketToOrderEnd(
        $Basket,
        QUI\ERP\Order\AbstractOrder $Order,
        QUI\ERP\Products\Product\ProductList $Products
    ): void {
        $Payment = $Order->getPayment();

        if (!$Payment) {
            return;
        }

        if (!$Payment->hasPaymentFee()) {
            return;
        }

        $PriceFactor = $Payment->toPriceFactor(null, $Order);

        /*
        $PriceFactor = new QUI\ERP\Products\Utils\PriceFactor([
            'title' => $Payment->getPaymentFeeTitle(),
            'description' => '',
            'priority' => 1,
            'calculation' => QUI\ERP\Accounting\Calc::CALCULATION_COMPLEMENT,
            'basis' => QUI\ERP\Accounting\Calc::CALCULATION_BASIS_CURRENTPRICE,
            'value' => $Payment->getPaymentFee(),
            'visible' => true,
            'currency' => $Order->getCurrency()->getCode()
        ]);
        */

        $PriceFactors = $Products->getPriceFactors();

        if ($PriceFactors === null) {
            return;
        }

        $PriceFactors->addToEnd($PriceFactor);

        try {
            $Products->recalculation();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $Order->getArticles()->calc();

        self::saveOrder($Order);
    }

    public static function onUpdateEnd(): void
    {
        QUI\Cache\Manager::clear('package/quiqqer/payments/provider');
    }

    private static function getLocaleText(QUI\Locale $Locale, string $language, string $variable): string
    {
        $value = $Locale->getByLang($language, 'quiqqer/payments', $variable);

        return is_string($value) ? $value : '';
    }

    private static function saveOrder(object $order): void
    {
        if (method_exists($order, 'save')) {
            $order->save();
        }
    }
}
