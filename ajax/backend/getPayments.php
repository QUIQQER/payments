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

        $current = QUI::getLocale()->getCurrent();

        usort($result, function ($a, $b) use ($current) {
            $aTitle = $a['title'][$current];
            $bTitle = $b['title'][$current];

            if (!empty($a['workingTitle'][$current])) {
                $aTitle = $a['workingTitle'][$current];
            }

            if (!empty($b['workingTitle'][$current])) {
                $bTitle = $b['workingTitle'][$current];
            }

            return strcmp($aTitle, $bTitle);
        });

        return $result;
    },
    false,
    'Permission::checkAdminUser'
);
