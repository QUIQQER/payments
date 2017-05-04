<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\EventHandling
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\Package\Package;

/**
 * Class EventHandling
 *
 * @package QUI\ERP\Accounting\Payments
 */
class EventHandling
{
    /**
     * @param Package $Package
     * @param array $params
     */
    public static function onPackageConfigSave(Package $Package, $params)
    {
        if ($Package->getName() !== 'quiqqer/payments') {
            return;
        }

        if (!isset($params['payments'])
            || !isset($params['payments']['paymentsJson'])
        ) {
            return;
        }

        $Settings = Settings::getInstance();
        $Payments = Handler::getInstance();

        if (empty($params['payments']['paymentsJson'])) {
            $Settings->removeSection('payments');
            $Settings->save();
            return;
        }

        $settings = json_decode($params['payments']['paymentsJson'], true);

        foreach ($settings as $payment => $status) {
            try {
                $Payments->getPayment($payment);
                $Settings->set('payments', $payment, $status ? 1 : 0);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addWarning($Exception->getMessage());
            }
        }

        $Settings->save();
    }
}