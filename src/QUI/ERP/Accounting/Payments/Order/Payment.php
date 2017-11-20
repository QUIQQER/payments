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
    public function __construct($attributes = array())
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
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $Orders = QUI\ERP\Order\Handler::getInstance();
        $Order  = $Orders->getOrderInProcess($this->getAttribute('orderId'));

        $Customer = $Order->getCustomer();
        $User     = QUI::getUserBySession();

        $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
        $payments = $Payments->getUserPayments($User);
        $Payment  = $Order->getPayment();

        $Engine->assign(array(
            'User'            => $User,
            'Customer'        => $Customer,
            'SelectedPayment' => $Payment,
            'payments'        => $payments
        ));

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
            throw new QUI\ERP\Order\Exception(array(
                'quiqqer/order',
                'exception.missing.payment'
            ));
        }
    }

    /**
     * Save the payment to the order
     */
    public function save()
    {
        if (!isset($_REQUEST['payment'])) {
            return;
        }

        $User  = QUI::getUserBySession();
        $Order = $this->getOrder();

        try {
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $Payment  = $Payments->getPayment($_REQUEST['payment']);
            $Payment->canUsedBy($User);
        } catch (QUI\ERP\Accounting\Payments\Exception $Payments) {
            return;
        }

        $Order->setPayment($Payment->getId());
        $Order->save();
    }
}
