<?php

/**
 * This File contains iPayment
 */

namespace QUI\ERP\Accounting\Payments;

use QUI\ERP\User;

/**
 * Interface for a PaymentModule
 * All Payment modules must implement this interface
 */
interface PaymentsInterface
{
    // template methods
    public function getOrderSuccessTpl($Bill, $Project = false);

    public function getPaymentUserDataTpl(User $User);

    public function getEditUserDataTpl(User $User);

    // text methods
    public function getOrderMailText();

    public function getBillText();

    // data methods
    public function getPaymentUserData($User = false);

    public function setPaymentUserData(array $params);

    // settings
    public function getSettings();

    public function getSetting($name);

    /**
     * Check if all the needed data are available
     *
     * @param User $User
     */
    public function checkUserData(User $User);
}
