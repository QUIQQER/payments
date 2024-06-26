<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Api\PaymentsInterface
 */

namespace QUI\ERP\Accounting\Payments\Api;

/**
 * Interface for a PaymentModule
 * All Payment modules must implement this interface
 */
interface PaymentsInterface
{
    /**
     * @return mixed
     */
    public function getName(): mixed;

    /**
     * @return string
     */
    public function getTitle(): string;

    /**
     * @return string
     */
    public function getDescription(): string;
}
