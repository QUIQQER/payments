<?php

namespace QUI\ERP\Order;

interface OrderInterface
{
    public function getArticles(): \QUI\ERP\Accounting\ArticleList;

    public function getCurrency(): \QUI\ERP\Currency\Currency;

    public function getCustomer(): ?\QUI\ERP\User;

    public function getId(): int;

    public function getPaidStatusInformation(): array;

    public function getPayment(): ?\QUI\ERP\Accounting\Payments\Types\Payment;

    public function getShipping(): ?\QUI\ERP\Shipping\Types\ShippingEntry;

    public function getUUID(): string;
}
