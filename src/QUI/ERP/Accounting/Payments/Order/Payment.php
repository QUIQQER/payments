<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Order\Payment
 */

namespace QUI\ERP\Accounting\Payments\Order;

use QUI;
use QUI\ERP\Accounting\Payments\Types\Factory;

use function array_filter;
use function array_values;
use function count;
use function dirname;
use function explode;
use function in_array;

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
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->addCSSFile(dirname(__FILE__) . '/Payment.css');
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getName($Locale = null): string
    {
        return 'Payment';
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return 'fa-money';
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = QUI::getUserBySession();

        $Order = $this->getOrder();
        $Order->recalculate();

        $Currency = $Order->getCurrency();
        $Customer = $Order->getCustomer();
        $SelectedPayment = $Order->getPayment();
        $payments = $this->getPaymentList();

        foreach ($payments as $PaymentEntry) {
            $PaymentEntry->setAttribute('Order', $Order);
        }

        $Engine->assign([
            'User' => $User,
            'Customer' => $Customer,
            'Currency' => $Currency,
            'SelectedPayment' => $SelectedPayment,
            'payments' => $payments,
            'this' => $this
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/Payment.html');
    }

    /**
     * @param QUI\ERP\Accounting\Payments\Types\Payment $Payment
     * @return bool
     */
    public function isSupported(QUI\ERP\Accounting\Payments\Types\Payment $Payment): bool
    {
        $Order = $this->getOrder();

        // currencies
        $currencies = $Payment->getAttribute('currencies');

        if (empty($currencies)) {
            return true;
        }

        $currencies = explode(',', $currencies);
        $currencies = array_filter($currencies);
        $OrderCurrency = $Order->getCurrency();

        if (!in_array($OrderCurrency->getCode(), $currencies)) {
            return false;
        }

        return true;
    }

    /**
     * @throws QUI\ERP\Order\Exception
     */
    public function validate(): void
    {
        $Order = $this->getOrder();
        $Payment = $Order->getPayment();
        $paymentList = $this->getPaymentList();

        if ($Payment === null && count($paymentList) === 1) {
            try {
                $Order->setPayment($paymentList[0]->getId());

                if (method_exists($Order, 'save')) {
                    $Order->save();
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }

            $Payment = $Order->getPayment();
        }

        if ($Payment === null && !empty($paymentList)) {
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
    public function save(): void
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

        $Order = $this->getOrder();
        $User = QUI::getUserBySession();

        try {
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $Payment = $Payments->getPayment($payment);
            $Payment->canUsedBy($User);
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $Order->setPayment($Payment->getId());

        if (method_exists($Order, 'save')) {
            $Order->save();
        }
    }

    /**
     * return the available payment list
     *
     * @return QUI\ERP\Accounting\Payments\Types\Payment[]
     */
    protected function getPaymentList(): array
    {
        $Order = $this->getOrder();
        $Articles = $Order->getArticles();
        $User = QUI::getUserBySession();

        $payments = [];
        $calculations = $Articles->getCalculations();
        // leave this line even if it's strange
        // floatval sum === 0 doesn't work -> floatval => float, 0 = int
        if ($calculations['sum'] >= 0 && $calculations['sum'] <= 0) {
            $payments[] = new QUI\ERP\Accounting\Payments\Methods\Free\PaymentType(0, new Factory());
        } else {
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $payments = $Payments->getUserPayments($User);

            $payments = array_filter($payments, function ($Payment) use ($Order) {
                /* @var $Payment QUI\ERP\Accounting\Payments\Types\Payment */
                if ($Payment->canUsedInOrder($Order) === false) {
                    return false;
                }

                return $Payment->getPaymentType()->isVisible($Order);
            });

            $payments = array_values($payments);
        }

        return $payments;
    }
}
