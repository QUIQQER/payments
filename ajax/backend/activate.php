<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_activate
 */

use \QUI\ERP\Accounting\Payments\Types\Factory;

/**
 * Activate a payment
 *
 * @param integer $paymentId
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_activate',
    function ($paymentId) {
        $Payments = new Factory();
        $Payment  = $Payments->getChild($paymentId);
        $Payment->activate();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/payments',
                'message.payment.activate.successfully',
                ['payment' => $Payment->getTitle()]
            )
        );

        return $Payment->toArray();
    },
    ['paymentId'],
    'Permission::checkAdminUser'
);
