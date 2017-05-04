<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getAvailablePayments
 */

use QUI\ERP\Accounting\Payments;

/**
 * Return all available payments
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_getAvailablePayments',
    function () {
        $payments = Payments\Handler::getInstance()->getAvailablePayments();

        return array_map(function ($Payment) {
            /* @var $Payment Payments\Api\AbstractPayment */
            return $Payment->toArray();
        }, $payments);
    },
    false,
    'Permission::checkAdminUser'
);
