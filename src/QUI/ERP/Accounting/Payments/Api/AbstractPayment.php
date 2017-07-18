<?php

/**
 * This file contains \QUI\ERP\Accounting\Payments\Api\Payment
 */

namespace QUI\ERP\Accounting\Payments\Api;

use QUI;

/**
 * Payment abstract class
 * This is the parent payment class for all payment methods
 *
 * @author www.pcsg.de (Henning Leutz)
 * @todo überarbeiten, da alte api mit integriert ist
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
    protected $paymentFields = array();

    /**
     * default settings
     *
     * @var array
     */
    protected $defaults = array();

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
     * Return the payment icon (the URL path)
     * Can be overwritten
     *
     * @return string
     */
    public function getIcon()
    {
        return URL_OPT_DIR . 'quiqqer/payments/bin/payments/default.png';
    }

    /**
     * Return the payment as an array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'name'        => $this->getName(),
            'title'       => $this->getTitle(),
            'description' => $this->getDescription()
        );
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



    //region OLD METHODS

    /**
     * Return the payments fields for the user
     * Like extra data
     *
     * @param QUI\ERP\User|null $User
     * @return array
     */
    public function getPaymentUserData($User = null)
    {
        return $this->paymentFields;
    }

    /**
     * Set the payment fields
     * In accounting -> paymentfields
     *
     * @param array $params
     */
    public function setPaymentUserData(array $params)
    {
        foreach ($params as $key => $value) {
            $this->paymentFields[$key] = $value;
        }
    }


    /**
     * The check method
     * Checks if all required fields are available and the payment can be executed
     *
     * @param QUI\ERP\User $User
     */
    public function checkUserData(QUI\ERP\User $User)
    {
        // nothing
    }

    /**
     * Return a setting
     *
     * @return array
     * @todo get own settings
     */
    public function getSettings()
    {
//        $settings = Plugin_payment::getConf()->get(
//            strtolower($this->getAttribute('name'))
//        );
//
//        return !empty($settings) ? $settings : array();
        return array();
    }

    /**
     * Return a setting from the payment
     *
     * @param string $name
     *
     * @return string|int|float|array|null
     */
    public function getSetting($name)
    {
        $settings = $this->getSettings();

        if (isset($settings[$name])) {
            return $settings[$name];
        }

        if (isset($this->defaults[$name])) {
            return $this->defaults[$name];
        }

        return null;
    }

    /**
     * Return the Success Type of the Payment
     * When is the payment successful
     *
     * @return int - Handler::SUCCESS_TYPE_*
     */
    public function getSuccessType()
    {
        return self::SUCCESS_TYPE_PAY;
    }

    /**
     * Template Methods
     */

    /**
     * Return the Template if the order is successfull
     * eq: The Basket display this Template on a successful payment
     *
     * @return String
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
     */
    public function getPaymentUserDataTpl(QUI\ERP\User $User)
    {
        if ($this->getSetting('icon')) {
            return '<img
                    class="plugin-payment-image-confirm"
                    src="' . URL_OPT_DIR . 'payment/moduls/' . strtolower($this->getSetting('name')) . '/bin/' . $this->getSetting('icon') . '" />';
        }

        return '';
    }

    /**
     * Display the needed data for editing
     *
     * @param QUI\ERP\User $User
     * @return String
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
     */
    public function getOrderMailText()
    {
        return $this->getLocale()->get(
            'plugin/payment',
            strtolower($this->getSetting('name')) . '.order.mailtext'
        );
    }

    /**
     * Rechungstext erweiterung
     *
     * @return String
     */
    public function getBillText()
    {
        return $this->getLocale()->get(
            'plugin/payment',
            strtolower($this->getSetting('name')) . '.bill.text'
        );
    }
    //endregion
}
