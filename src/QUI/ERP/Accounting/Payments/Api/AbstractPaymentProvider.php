<?php

/**
 * This file contains \QUI\ERP\Accounting\Payments\Api\Payment
 */

namespace QUI\ERP\Accounting\Payments\Api;

use QUI;

/**
 * Payment provider
 * @author www.pcsg.de (Henning Leutz)
 */
abstract class AbstractPaymentProvider
{
    /**
     * @return array
     */
    abstract public function getPaymentMethods();
}
