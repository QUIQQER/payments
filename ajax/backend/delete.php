<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getPayments
 */

use \QUI\ERP\Accounting\Payments\Types\Factory;

/**
 * Return all active payments
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_delete',
    function ($paymentId) {
        $Payments = new Factory();
        $Payments->getChild($paymentId)->delete();
    },
    ['paymentId'],
    'Permission::checkAdminUser'
);
