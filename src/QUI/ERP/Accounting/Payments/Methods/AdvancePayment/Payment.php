<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment
 */

namespace QUI\ERP\Accounting\Payments\Methods\AdvancePayment;

use QUI;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Order\Handler as OrderHandler;

/**
 * Class Payment
 * - Vorkasse
 *
 * @package QUI\ERP\Accounting\Payments\Methods\Invoice\Payment
 */
class Payment extends QUI\ERP\Accounting\Payments\Api\AbstractPayment
{
    /**
     * @return array|string
     */
    public function getTitle()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.advanced.payment.title'
        );
    }

    /**
     * @return array|string
     */
    public function getDescription()
    {
        return $this->getLocale()->get(
            'quiqqer/payments',
            'payment.advanced.payment.description'
        );
    }

    /**
     * @param $hash
     * @return bool
     */
    public function isSuccessful($hash)
    {
        return true;
    }

    /**
     * Is the payment approved?
     *
     * Approved = The payment amount is considered to be safe for payment
     *
     * IMPORTANT: This does NOT mean that actual money was transferred!
     *
     * @param string $hash - Vorgangsnummer - hash number - procedure number
     * @return bool
     */
    public function isApproved($hash)
    {
        try {
            $Order = OrderHandler::getInstance()->getOrderByHash($hash);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        return $Order->isPaid();
    }

    /**
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return Payments::getInstance()->getHost() .
            URL_OPT_DIR .
            'quiqqer/payments/bin/payments/Vorkasse.png';
    }

    /**
     * Return the extra text for the invoice
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|QUI\ERP\Accounting\Invoice\InvoiceTemporary|QUI\ERP\Accounting\Invoice\InvoiceView $Invoice
     * @return string
     */
    public function getInvoiceInformationText($Invoice)
    {
        return QUI::getLocale()->get('quiqqer/payments', 'invoice.information.text.advancedPayment');
    }
}
