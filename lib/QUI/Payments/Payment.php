<?php

/**
 * This file contains \QUI\Payments\
 */

namespace QUI\Payments;

/**
 * Payment abstract class
 * This is the parent payment class for all payment methods
 *
 * @author www.pcsg.de (Henning Leutz)
 */

abstract class Payment
{
    /**
     * payment fields - extra fields for the payment / accounting
     *
     * @var Array
     */
    protected $_paymentfields = array();

    /**
     * default settings
     * @var Array
     */
    protected $_defaults = array();

    /**
     * Locale object for the payment
     * @var Locale
     */
    protected $_Locale = null;

    /**
     * Return the payments fields for the user
     * Like extra data
     *
     * @param string $User
     * @return array
     */
    public function getPaymentUserData($User=false)
    {
        return $this->_paymentfields;
    }

    /**
     * Set the payment fields
     * In accounting -> paymentfields
     *
     * @param Array $params
     */
    public function setPaymentUserData(array $params)
    {
        foreach ( $params as $key => $value ) {
            $this->_paymentfields[ $key ] = $value;
        }
    }

    /**
     * Set the locale object to the payment
     *
     * @param Locale $Locale
     */
    public function setLocale(Locale $Locale)
    {
        $this->_Locale = $Locale;
    }

    /**
     * Return the Locale of the payment
     *
     * @return Locale
     */
    public function getLocale()
    {
        if ( !$this->_Locale ) {
            $this->_Locale = \QUI::getLocale();
        }

        return $this->_Locale;
    }

    /**
     * The check method
     * Checks if all required fields are available and the payment can be executed
     *
     * @param \QUI\Users\User $User
     */
    public function checkUserData(\QUI\Users\User $User)
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
        $settings = Plugin_payment::getConf()->get(
            strtolower( $this->getAttribute( 'name' ) )
        );

        return !empty( $settings ) ? $settings : array();
    }

    /**
     * Return a setting from the payment
     *
     * @param unknown $name
     *
     * @return unknown_type|null
     */
    public function getSetting($name)
    {
        $settings = $this->getSettings();

        if ( isset( $settings[ $name ] ) ) {
            return $settings[ $name ];
        }

        if ( isset( $this->_defaults[ $name ] ) ) {
            return $this->_defaults[ $name ];
        }

        return null;
    }

    /**
     * Return the Success Type of the Payment
     * When is the payment successful
     *
     * @return number Plugin_payment::SUCCESS_TYPE_*
     */
    public function getSuccessType()
    {
        return \QUI\Payments\Manager::SUCCESS_TYPE_PAY;
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
    public function getOrderSuccessTpl($Bill, $Project=false)
    {
        return '';
    }

    /**
     * Display the needed data
     * eq: The Basket display this template at the order
     *
     * @param User $User
     * @return String
     */
    public function getPaymentUserDataTpl(User $User)
    {
        if ( $this->getAttribute('icon') )
        {
            return '<img
                    class="plugin-payment-image-confirm"
                    src="'. URL_OPT_DIR .'payment/moduls/'. strtolower($this->getAttribute('name')) .'/bin/'. $this->getAttribute('icon') .'" />';

        }

        return '';
    }

    /**
     * Display the needed data for editing
     *
     * @param User $User
     * @return String
     */
    public function getEditUserDataTpl(User $User)
    {
        return '';
    }

    /**
     * Tpl für den User im Adminbereich
     *
     * @return string
     */
    public function getAdminDataTpl(User $User)
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
            strtolower($this->getAttribute('name')) .'.order.mailtext'
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
            strtolower($this->getAttribute('name')) .'.bill.text'
        );
    }
}
