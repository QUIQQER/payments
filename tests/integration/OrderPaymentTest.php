<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI\ERP\Accounting\Payments\Order\Payment as PaymentStep;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Order\AbstractOrder;

class OrderPaymentTest extends SqlitePaymentTestCase
{
    /** @var array<string, mixed> */
    private array $originalRequest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRequest = $_REQUEST;
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $_REQUEST = $this->originalRequest;

        parent::tearDown();
    }

    public function testSaveRejectsPaymentThatCurrentUserCannotUse(): void
    {
        $paymentId = $this->insertPayment(['active' => 0]);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->expects(self::never())->method('setPayment');
        $Step = new PaymentStep(['Order' => $Order]);
        $_REQUEST['payment'] = $paymentId;

        $Step->save();
    }

    public function testSaveIgnoresUnknownPaymentId(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->expects(self::never())->method('setPayment');
        $Step = new PaymentStep(['Order' => $Order]);
        $_REQUEST['payment'] = 999999;

        $Step->save();
    }

    public function testSaveAssignsUsablePaymentToOrder(): void
    {
        $paymentId = $this->insertPayment(['active' => 1]);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->expects(self::once())->method('setPayment')->with($paymentId);
        $Step = new PaymentStep(['Order' => $Order]);
        $_REQUEST['payment'] = $paymentId;

        $Step->save();
    }
}
