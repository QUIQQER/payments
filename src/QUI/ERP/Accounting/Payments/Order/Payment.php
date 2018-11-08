<?php

/**
 * This file contains QUI\ERP\Order\Controls\Payment
 */

namespace QUI\ERP\Accounting\Payments\Order;

use QUI;

/**
 * Class Payment
 *
 * @package QUI\ERP\Order\Controls
 */
class Payment extends QUI\ERP\Order\Controls\AbstractOrderingStep
{
    /**
     * Payment constructor.
     *
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__).'/Payment.css');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null)
    {
        return 'Payment';
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'fa-money';
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User   = QUI::getUserBySession();

        $Order = $this->getOrder();
        $Order->recalculate();

        $Customer = $Order->getCustomer();
        $Payment  = $Order->getPayment();
        $Articles = $Order->getArticles();

        $calculations = $Articles->getCalculations();
        $payments     = [];

        // leave this line even if it's curios
        // floatval sum === 0 doesn't work -> floatval => float, 0 = int
        if ($calculations['sum'] >= 0 && $calculations['sum'] <= 0) {
            $payments[] = new QUI\ERP\Accounting\Payments\Methods\Free\PaymentType();
        } else {
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $payments = $Payments->getUserPayments($User);

            $payments = array_filter($payments, function ($Payment) {
                /* @var $Payment QUI\ERP\Accounting\Payments\Types\Payment */
                return $Payment->getPaymentType()->isVisible();
            });
        }

        $Engine->assign([
            'User'            => $User,
            'Customer'        => $Customer,
            'SelectedPayment' => $Payment,
            'payments'        => $payments
        ]);

        return $Engine->fetch(dirname(__FILE__).'/Payment.html');
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate()
    {
        $Order   = $this->getOrder();
        $Payment = $Order->getPayment();

        if ($Payment === null) {
            throw new QUI\ERP\Order\Exception([
                'quiqqer/order',
                'exception.missing.payment'
            ]);
        }

        // @todo validate customer payment data
    }

    /**
     * Save the payment to the order
     *
     * @throws QUI\ERP\Order\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function save()
    {
        $payment = false;

        if (isset($_REQUEST['payment'])) {
            $payment = $_REQUEST['payment'];
        }

        if (empty($payment) && $this->getAttribute('payment')) {
            $payment = $this->getAttribute('payment');
        }

        if (empty($payment)) {
            return;
        }


        $User  = QUI::getUserBySession();
        $Order = $this->getOrder();

        try {
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $Payment  = $Payments->getPayment($payment);
            $Payment->canUsedBy($User);
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $Order->setPayment($Payment->getId());
        $Order->save();
    }
}
