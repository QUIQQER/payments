<?php

/**
 * This file contains package_quiqqer_payments_ajax_backend_update
 */

use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Payments;

/**
 * Update a payment
 *
 * @param integer $paymentId - Payment ID
 * @param array $data - Payment Data
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_payments_ajax_backend_update',
    function ($paymentId, $data) {
        $Payments = new Factory();
        $Payment  = $Payments->getChild($paymentId);
        $data     = \json_decode($data, true);

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

        if (isset($data['orderInformation'])) {
            $Payment->setOrderInformation($data['orderInformation']);
        }

        if (isset($data['paymentFeeTitle'])) {
            $Payment->setPaymentFeeTitle($data['paymentFeeTitle']);
        }

        if (isset($data['paymentFee'])) {
            $Payment->setPaymentFee($data['paymentFee']);
        } else {
            $Payment->clearPaymentFee();
        }

        if (isset($data['icon'])) {
            $Payment->setIcon($data['icon']);
        }

        $Payment->setAttributes($data);
        $Payment->update();

        QUI::getMessagesHandler()->addSuccess(
            QUI::getLocale()->get(
                'quiqqer/payments',
                'message.payment.saved.successfully',
                [
                    'payment' => $Payment->getTitle()
                ]
            )
        );

        return $Payment->toArray();
    },
    ['paymentId', 'data'],
    'Permission::checkAdminUser'
);
