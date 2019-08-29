<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getPayments
 */

use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Payment;

/**
 * Return all active payments
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_getPayments',
    function () {
        $payments = Payments::getInstance()->getPayments();
        $result   = [];

        foreach ($payments as $Payment) {
            /* @var $Payment Payment */
            $result[] = $Payment->toArray();
        }

        return $result;
    },
    false,
    'Permission::checkAdminUser'
);
