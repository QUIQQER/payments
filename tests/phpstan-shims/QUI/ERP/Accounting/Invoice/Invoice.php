<?php

namespace QUI\ERP\Accounting\Invoice;

class Invoice
{
    public function addHistory(string $message): void
    {
    }

    public function getAttribute(string $key): mixed
    {
        return null;
    }

    public function getCustomer(): ?\QUI\ERP\User
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function getPaidStatusInformation(): array
    {
        return [];
    }

    public function isPaid(): bool
    {
        return false;
    }
}
