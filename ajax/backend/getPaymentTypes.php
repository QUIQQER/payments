<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getPaymentMethods
 */

use QUI\ERP\Accounting\Payments\Payments;

/**
 * Return all active payments
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_getPaymentTypes',
    function () {
        return array_map(function ($Payment) {
            return $Payment->toArray();
        }, Payments::getInstance()->getPaymentTypes());
    },
    false,
    'Permission::checkAdminUser'
);
