<?php

/**
 * This file contains \QUI\ERP\Accounting\Payments\Api\Payment
 */

namespace QUI\ERP\Accounting\Payments\Api;

use QUI;
use QUI\ERP\Order\AbstractOrder;

/**
 * Payment abstract class
 * This is the parent payment class for all payment methods
 *
 * @author www.pcsg.de (Henning Leutz)
 * @todo überarbeiten, da alte api mit integriert ist
 * @todo AbstractPaymentMethod
 */
abstract class AbstractPayment implements PaymentsInterface
{
    /**
     * @var int
     */
    const SUCCESS_TYPE_PAY = 1;

    /**
     * @var int
     */
    const SUCCESS_TYPE_BILL = 2;

    /**
     * payment fields - extra fields for the payment / accounting
     *
     * @var array
     */
    protected $paymentFields = [];

    /**
     * default settings
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Locale object for the payment
     *
     * @var QUI\Locale
     */
    protected $Locale = null;

    /**
     * Set the locale object to the payment
     *
     * @param QUI\Locale $Locale
     */
    public function setLocale(QUI\Locale $Locale)
    {
        $this->Locale = $Locale;
    }

    /**
     * Return the Locale of the payment
     *
     * @return QUI\Locale
     */
    public function getLocale()
    {
        if ($this->Locale === null) {
            $this->Locale = QUI::getLocale();
        }

        return $this->Locale;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return md5(get_class($this));
    }

    /**
     * @return string
     */
    abstract public function getTitle();

    /**
     * @return string
     */
    abstract public function getDescription();

    /**
     * Is the payment successful?
     * This method returns the payment success type
     *
     * @param string $hash - Vorgangsnummer - hash number - procedure number
     * @return bool
     */
    abstract public function isSuccessful($hash);

    /**
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return URL_OPT_DIR.'quiqqer/payments/bin/payments/default.png';
    }

    /**
     * Return the payment as an array
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'name'        => $this->getName(),
            'title'       => $this->getTitle(),
            'description' => $this->getDescription()
        ];
    }

    /**
     * Is the payment a gateway payment?
     *
     * @return bool
     */
    public function isGateway()
    {
        return false;
    }

    /**
     * Is the payment be visible in the frontend?
     * Every payment method can determine this by itself (API for developers)
     *
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

    /**
     * This flag indicates whether the payment module can be created more than once
     *
     * @return bool
     */
    public function isUnique()
    {
        return true;
    }

    /**
     * @return bool
     */
    public function refundSupport()
    {
        return false;
    }

    /**
     * Execute a refund
     *
     * @param QUI\ERP\Accounting\Payments\Transactions\Transaction $Transaction
     */
    public function refund(QUI\ERP\Accounting\Payments\Transactions\Transaction $Transaction)
    {
    }

    /**
     * If the Payment method is a payment gateway, it can return a gateway display
     *
     * @param AbstractOrder $Order
     * @param QUI\ERP\Order\Controls\AbstractOrderingStep|null $Step
     * @return string
     */
    public function getGatewayDisplay(AbstractOrder $Order, $Step = null)
    {
        return '';
    }

    /**
     * Execute the request from the payment provider
     *
     * @param QUI\ERP\Accounting\Payments\Gateway\Gateway $Gateway
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function executeGatewayPayment(QUI\ERP\Accounting\Payments\Gateway\Gateway $Gateway)
    {
    }





    //region OLD METHODS

    /**
     * Template Methods
     */

    /**
     * Return the Template if the order is successfull
     * eq: The Basket display this Template on a successful payment
     *
     * @return String
     * @deprecated
     */
    public function getOrderSuccessTpl($Bill, $Project = false)
    {
        return '';
    }

    /**
     * Display the needed data
     * eq: The Basket display this template at the order
     *
     * @param QUI\ERP\User $User
     * @return String
     * @deprecated
     */
    public function getPaymentUserDataTpl(QUI\ERP\User $User)
    {
        if ($this->getSetting('icon')) {
            return '<img
                    class="plugin-payment-image-confirm"
                    src="'.URL_OPT_DIR.'payment/moduls/'.strtolower($this->getSetting('name')).'/bin/'.$this->getSetting('icon').'" />';
        }

        return '';
    }

    /**
     * Display the needed data for editing
     *
     * @param QUI\ERP\User $User
     * @return String
     * @deprecated
     */
    public function getEditUserDataTpl(QUI\ERP\User $User)
    {
        return '';
    }

    /**
     * Tpl für den User im Adminbereich
     *
     * @param QUI\ERP\User $User
     * @return string
     * @deprecated
     */
    public function getAdminDataTpl(QUI\ERP\User $User)
    {
        return '';
    }

    /**
     * Text messages
     */

    /**
     * Zusätzlicher bestätigungsmail Text
     *
     * @return String
     * @deprecated
     */
    public function getOrderMailText()
    {
        return $this->getLocale()->get(
            'plugin/payment',
            strtolower($this->getSetting('name')).'.order.mailtext'
        );
    }

    /**
     * Rechungstext erweiterung
     *
     * @return String
     * @deprecated
     */
    public function getBillText()
    {
        return $this->getLocale()->get(
            'plugin/payment',
            strtolower($this->getSetting('name')).'.bill.text'
        );
    }
    //endregion
}
