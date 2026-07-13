<?php

namespace QUI\ERP\Order\Controls;

abstract class AbstractOrderingStep extends \QUI\Control
{
    public function getOrder(): ?\QUI\ERP\Order\AbstractOrder
    {
        return null;
    }
}
