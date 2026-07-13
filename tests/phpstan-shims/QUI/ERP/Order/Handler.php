<?php

namespace QUI\ERP\Order;

class Handler
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function get(int | string $orderId): Order | OrderInProcess
    {
        throw new \LogicException('PHPStan shim');
    }

    public function getOrderByHash(string $hash): Order | OrderInProcess
    {
        throw new \LogicException('PHPStan shim');
    }

    public function getOrderInProcess(int | string $orderId): OrderInProcess
    {
        throw new \LogicException('PHPStan shim');
    }
}
