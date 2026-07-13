<?php

namespace QUI\ERP\Order;

abstract class AbstractOrder implements OrderInterface
{
    public function addHistory(string $message): void
    {
    }

    public function getArticles(): \QUI\ERP\Accounting\ArticleList
    {
        throw new \LogicException('PHPStan shim');
    }

    public function getCurrency(): \QUI\ERP\Currency\Currency
    {
        throw new \LogicException('PHPStan shim');
    }

    public function getCustomer(): ?\QUI\ERP\User
    {
        return null;
    }

    public function getId(): int
    {
        return 0;
    }

    public function getInvoice(): \QUI\ERP\Accounting\Invoice\Invoice | \QUI\ERP\Accounting\Invoice\InvoiceTemporary
    {
        return new \QUI\ERP\Accounting\Invoice\Invoice();
    }

    public function getPaidStatusInformation(): array
    {
        return [];
    }

    public function getPayment(): ?\QUI\ERP\Accounting\Payments\Types\Payment
    {
        return null;
    }

    public function getPriceCalculation(): \QUI\ERP\Accounting\Calculations
    {
        throw new \LogicException('PHPStan shim');
    }

    public function getShipping(): ?\QUI\ERP\Shipping\Types\ShippingEntry
    {
        return null;
    }

    public function getUUID(): string
    {
        return '';
    }

    public function isPosted(): bool
    {
        return false;
    }

    public function isPaid(): bool
    {
        return false;
    }

    public function isSuccessful(): bool
    {
        return false;
    }

    public function recalculate(): void
    {
    }

    public function save(): void
    {
    }

    public function setPayment(int | string $paymentId): void
    {
    }
}
