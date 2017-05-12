<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Api\PaymentsInterface
 */

namespace QUI\ERP\Accounting\Payments\Api;

use QUI\ERP\User;

/**
 * Interface for a PaymentModule
 * All Payment modules must implement this interface
 */
interface PaymentsInterface
{
    /**
     * @return mixed
     */
    public function getName();

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getDescription();

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
