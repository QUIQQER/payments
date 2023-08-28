<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getPayment
 */

use QUI\ERP\Accounting\Payments\Payments;

/**
 * Return all active payments
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_getPayment',
    function ($paymentId) {
        $Payment = Payments::getInstance()->getPayment($paymentId);
        $payment = $Payment->toArray();
        $payment['icon'] = $Payment->getAttribute('icon');

        return $payment;
    },
    ['paymentId'],
    'Permission::checkAdminUser'
);
