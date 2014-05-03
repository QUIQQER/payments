<?php

/**
 * This File contains iPayment
 */

namespace QUI\Payments\Interfaces;

/**
 * Interace for a PaymentModule
 * All Payment modules must implement this interface
 *
 * @author www.pcsg.de
 */
interface Payment
{
    // template methods
    public function getOrderSuccessTpl($Bill, $Project=false);
    public function getPaymentUserDataTpl(\QUI\Users\User $User);
    public function getEditUserDataTpl(User $User);

    // text methods
    public function getOrderMailText();
    public function getBillText();

    // data methods
    public function getPaymentUserData($User=false);
    public function setPaymentUserData(array $params);

    // settings
    public function getSettings();
    public function getSetting($name);

    /**
     * Check if all the needed data are available
     *
     * @param \QUI\Users\User $User
     */
    public function checkUserData(\QUI\Users\User $User);
}
