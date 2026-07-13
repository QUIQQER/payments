<?php

namespace QUI\ERP\Shipping\Types;

if (!class_exists(ShippingEntry::class)) {
    class ShippingEntry
    {
        public function getTitle(): string
        {
            return '';
        }
    }
}
