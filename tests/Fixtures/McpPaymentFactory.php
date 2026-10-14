<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

class McpPaymentFactory extends RecordingPaymentFactory
{
    /** @var list<RecordingPayment> */
    public array $loadedPayments = [];

    public function getChildClass(): string
    {
        return RecordingPayment::class;
    }

    public function getChild($id): RecordingPayment
    {
        $Payment = parent::getChild($id);

        if (!$Payment instanceof RecordingPayment) {
            throw new \RuntimeException('Expected recording fixture payment.');
        }

        $this->loadedPayments[] = $Payment;

        return $Payment;
    }
}
