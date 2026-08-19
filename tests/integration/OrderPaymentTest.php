<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\Methods\Free\PaymentType as FreePaymentType;
use QUI\ERP\Accounting\Payments\Order\Payment as PaymentStep;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\TestablePaymentStep;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Currency\Currency;
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

    public function testSaveWithoutSelectionOrOrderHasNoSideEffects(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->expects(self::never())->method('setPayment');
        $Step = new PaymentStep(['Order' => $Order]);

        $Step->save();

        $StepWithAttribute = new PaymentStep(['payment' => 71]);
        $StepWithAttribute->save();
    }

    public function testStepMetadataAndMissingOrderBehaviour(): void
    {
        $Step = new PaymentStep();

        self::assertSame('Payment', $Step->getName());
        self::assertSame('fa-money', $Step->getIcon());
        self::assertFalse($Step->isSupported($this->createMock(Payment::class)));

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionMessage('No order is available');
        $Step->getBody();
    }

    public function testSupportedPaymentCurrenciesAreMatchedAgainstOrderCurrency(): void
    {
        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCurrency')->willReturn($Currency);
        $Step = new PaymentStep(['Order' => $Order]);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getAttribute')->willReturnMap([
            ['currencies', 'EUR,USD']
        ]);

        self::assertTrue($Step->isSupported($Payment));

        $Unsupported = $this->createMock(Payment::class);
        $Unsupported->method('getAttribute')->willReturnMap([
            ['currencies', 'GBP,USD']
        ]);
        self::assertFalse($Step->isSupported($Unsupported));

        $Unrestricted = $this->createMock(Payment::class);
        $Unrestricted->method('getAttribute')->willReturnMap([
            ['currencies', null]
        ]);
        self::assertTrue($Step->isSupported($Unrestricted));
    }

    public function testZeroValueOrderOffersOnlyFreePayment(): void
    {
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('getCalculations')->willReturn(['sum' => 0]);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Step = new TestablePaymentStep(['Order' => $Order]);

        $payments = $Step->realPaymentList();

        self::assertCount(1, $payments);
        self::assertInstanceOf(FreePaymentType::class, $payments[0]);
    }

    public function testPositiveValueOrderListsOnlyUsableVisiblePayments(): void
    {
        $paymentId = $this->insertPayment(['active' => 1]);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->method('getCalculations')->willReturn(['sum' => 25]);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getArticles')->willReturn($Articles);
        $Step = new TestablePaymentStep(['Order' => $Order]);

        $payments = $Step->realPaymentList();

        self::assertCount(1, $payments);
        self::assertSame($paymentId, $payments[0]->getId());
    }

    public function testValidateAutomaticallyAssignsOnlyAvailablePayment(): void
    {
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getId')->willReturn(71);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturnOnConsecutiveCalls(null, $Payment);
        $Order->expects(self::once())->method('setPayment')->with(71);
        $Step = new TestablePaymentStep(['Order' => $Order]);
        $Step->controlledPaymentList = [$Payment];

        $Step->validate();
    }

    public function testValidateRequiresAnOrder(): void
    {
        $this->expectException(QUI\ERP\Order\Exception::class);
        $this->expectExceptionMessage('No order is available');

        (new TestablePaymentStep())->validate();
    }

    public function testValidateReportsMissingSelectionAfterAutomaticAssignmentFails(): void
    {
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getId')->willReturn(71);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturn(null);
        $Order->expects(self::once())
            ->method('setPayment')
            ->with(71)
            ->willThrowException(new QUI\Exception('assignment failed'));
        $Step = new TestablePaymentStep(['Order' => $Order]);
        $Step->controlledPaymentList = [$Payment];

        $this->expectException(QUI\ERP\Order\Exception::class);
        $Step->validate();
    }

    public function testPaymentListWithoutOrderIsEmpty(): void
    {
        self::assertSame([], (new TestablePaymentStep())->realPaymentList());
    }

    public function testValidateRejectsMissingSelectionWhenSeveralPaymentsExist(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturn(null);
        $Step = new TestablePaymentStep(['Order' => $Order]);
        $Step->controlledPaymentList = [
            $this->createMock(Payment::class),
            $this->createMock(Payment::class)
        ];

        $this->expectException(QUI\ERP\Order\Exception::class);
        $Step->validate();
    }
}
