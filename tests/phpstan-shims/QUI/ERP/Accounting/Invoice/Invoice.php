<?php

namespace QUI\ERP\Accounting\Invoice;

class Invoice
{
    public function getAttribute(string $key): mixed
    {
        return null;
    }

    public function getCustomer(): ?\QUI\ERP\User
    {
        return null;
    }

    public function isPaid(): bool
    {
        return false;
    }
}
