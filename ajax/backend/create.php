<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getPayments
 */

use \QUI\ERP\Accounting\Payments\Types\Factory;

/**
 * Create a new payment method
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_create',
    function () {
        $Payments = new Factory();
        $Payment  = $Payments->createChild();

        return $Payment->getId();
    },
    false,
    'Permission::checkAdminUser'
);
