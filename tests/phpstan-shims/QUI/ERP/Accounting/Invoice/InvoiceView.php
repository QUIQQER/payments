<?php

namespace QUI\ERP\Accounting\Invoice;

class InvoiceView
{
    public function getCustomer(): ?\QUI\ERP\User
    {
        return null;
    }

    public function getInvoice(): Invoice | InvoiceTemporary
    {
        return new Invoice();
    }

    public function isPaid(): bool
    {
        return false;
    }
}
