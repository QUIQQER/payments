<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_getPayments
 */

use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Factory;

/**
 * Create a new payment method
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_create',
    function ($paymentType) {
        $Type = Payments::getInstance()->getPaymentType($paymentType);

        $Factory = new Factory();
        $Payment = $Factory->createChild([
            'payment_type' => get_class($Type)
        ]);

        return $Payment->getId();
    },
    ['paymentType'],
    'Permission::checkAdminUser'
);
