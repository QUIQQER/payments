<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;

class PaymentFeeRegressionTest extends SqlitePaymentTestCase
{
    public function testFloatPaymentFeeIsPersistedDuringUpdate(): void
    {
        $id = $this->insertPayment();
        $Payment = $this->loadPayment($id);

        $Payment->setPaymentFee(12.5);
        $Payment->update();

        $storedFee = $this->connection->fetchOne(
            'SELECT paymentFee FROM ' . $this->paymentTable() . ' WHERE id = ?',
            [$id]
        );

        self::assertEqualsWithDelta(12.5, (float)$storedFee, 0.00001);
        self::assertEqualsWithDelta(12.5, $this->loadPayment($id)->getPaymentFee(), 0.00001);
    }
}
