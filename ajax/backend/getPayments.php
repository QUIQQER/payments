<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getPayments
 */

use QUI\ERP\Accounting\Payments\Handler;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;

/**
 * Return all active payments
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_getPayments',
    function () {
        $payments = Handler::getInstance()->getPayments();

        return array_map(function ($Payment) {
            /* @var $Payment AbstractPayment */
            return $Payment->toArray();
        }, $payments);
    },
    false,
    'Permission::checkAdminUser'
);
