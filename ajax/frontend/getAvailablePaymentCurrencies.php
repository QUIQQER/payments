<?php

use QUI\ERP\Accounting\Payments\Payments;

QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_frontend_getAvailablePaymentCurrencies',
    function ($paymentId) {
        $Payment = Payments::getInstance()->getPayment($paymentId);
        $currencies = $Payment->getSupportedCurrencies();

        return array_map(function ($Currency) {
            return $Currency->toArray();
        }, $currencies);
    },
    ['paymentId']
);
