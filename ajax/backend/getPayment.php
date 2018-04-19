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
        return Payments::getInstance()->getPayment($paymentId)->toArray();
    },
    ['paymentId'],
    'Permission::checkAdminUser'
);
