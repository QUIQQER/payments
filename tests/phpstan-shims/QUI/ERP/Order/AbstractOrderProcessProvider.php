<?php

namespace QUI\ERP\Order;

abstract class AbstractOrderProcessProvider
{
    protected const PROCESSING_STATUS_FINISH = 100;
    protected const PROCESSING_STATUS_PROCESSING = 10;

    protected int $currentStatus = 0;
    protected bool $hasErrors = false;
}
