<?php

/**
 * This File contains \QUI\Payments\Exception
 */

namespace QUI\Payments;

/**
 * Exception for the payments
 *
 * @author www.pcsg.de (Henning Leutz)
 */

class Exception extends \QUI\Exception
{
    /**
     * constructor
     *
     * @param String $message - Text of the Exception
     * @param Integer $code - Errorcode of the Exception
     */
    public function __construct($message=null, $code=0)
    {
        parent::__construct($message, (int)$code);
    }
}
