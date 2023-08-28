<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_deactivate
 */

use QUI\ERP\Accounting\Payments\Types\Factory;

/**
 * Deactivate a payment
 *
 * @param integer $paymentId
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_deactivate',
    function ($paymentId) {
        $Payments = new Factory();
        $Payment = $Payments->getChild($paymentId);
        $Payment->deactivate();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/payments',
                'message.payment.deactivate.successfully',
                ['payment' => $Payment->getTitle()]
            )
        );

        return $Payment->toArray();
    },
    ['paymentId'],
    'Permission::checkAdminUser'
);
