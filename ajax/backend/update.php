<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_update
 */

use \QUI\ERP\Accounting\Payments\Types\Factory;

/**
 * Update a payment
 *
 * @param integer $paymentId - Payment ID
 * @param integer $paymentId - Payment ID
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_update',
    function ($paymentId, $data) {
        $Payments = new Factory();
        $Payment  = $Payments->getChild($paymentId);
        $data     = json_decode($data, true);

        /* @var $Payment QUI\ERP\Accounting\Payments\Types\Payment */
        if (isset($data['title'])) {
            $Payment->setTitle($data['title']);
        }

        if (isset($data['workingTitle'])) {
            $Payment->setWorkingTitle($data['workingTitle']);
        }

        if (isset($data['description'])) {
            $Payment->setDescription($data['description']);
        }

        $Payment->setAttributes($data);
        $Payment->update();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/payments',
                'message.payment.saved.successfully',
                array(
                    'payment' => $Payment->getTitle()
                )
            )
        );
    },
    array('paymentId', 'data'),
    'Permission::checkAdminUser'
);
