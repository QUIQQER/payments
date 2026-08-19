<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Exception as PaymentsException;
use QUI\ERP\Accounting\Payments\Gateway\Gateway;
use QUI\ERP\Accounting\Payments\OrderProcessProvider;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\AbstractOrderProcessProvider;
use QUI\ERP\Order\Order;
use QUI\ERP\Order\OrderInProcess;
use QUI\ERP\Order\OrderProcess;
use QUI\ERP\Order\ProcessingException;
use QUI\ERP\Order\Utils\OrderProcessSteps;
use ReflectionMethod;
use ReflectionProperty;

class GatewayAndProcessTest extends TestCase
{
    private Gateway $Gateway;

    /** @var array<string, mixed> */
    private array $originalRequest;

    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalRequest = $_REQUEST;
        $this->originalServer = $_SERVER;
        $_REQUEST = [];

        $this->Gateway = Gateway::getInstance();
        $this->setGatewayProperty('Order', null);
        $this->setGatewayProperty('gatewayPayment', false);
        $this->setGatewayProperty('isCancelRequest', false);
        $this->setGatewayProperty('isSuccessRequest', false);
    }

    protected function tearDown(): void
    {
        $this->setGatewayProperty('Order', null);
        $this->setGatewayProperty('gatewayPayment', false);
        $this->setGatewayProperty('isCancelRequest', false);
        $this->setGatewayProperty('isSuccessRequest', false);
        $_REQUEST = $this->originalRequest;
        $_SERVER = $this->originalServer;

        parent::tearDown();
    }

    public function testGatewayAcceptsBothPersistentAndInProcessOrders(): void
    {
        $FinalOrder = $this->createMock(Order::class);
        $this->Gateway->setOrder($FinalOrder);
        self::assertSame($FinalOrder, $this->Gateway->getOrder());

        $PendingOrder = $this->createMock(OrderInProcess::class);
        $this->Gateway->setOrder($PendingOrder);
        self::assertSame($PendingOrder, $this->Gateway->getOrder());
    }

    public function testGatewayExecutionRequiresAnAssignedPayment(): void
    {
        $this->expectException(PaymentsException::class);
        $this->expectExceptionMessage('No payment order is available');

        $this->Gateway->executeGatewayPayment();
    }

    public function testGatewayDelegatesExecutionToOrderPaymentType(): void
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->expects(self::once())
            ->method('executeGatewayPayment')
            ->with($this->Gateway);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturn($Payment);
        $this->setGatewayProperty('Order', $Order);

        $this->Gateway->executeGatewayPayment();
    }

    public function testGatewayRecordsPaymentExecutionExceptionsInOrderHistory(): void
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('executeGatewayPayment')
            ->willThrowException(new \QUI\Exception('Provider declined payment', 409));
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->expects(self::once())
            ->method('addHistory')
            ->with(self::callback(static function (string $history): bool {
                $data = json_decode($history, true);

                return $data === [
                    'message' => 'Provider declined payment',
                    'code' => 409
                ];
            }));
        $this->setGatewayProperty('Order', $Order);

        $this->Gateway->executeGatewayPayment();
    }

    public function testGatewayFlagsCanBeToggledIndependently(): void
    {
        self::assertFalse($this->Gateway->isGatewayPayment());
        self::assertFalse($this->Gateway->isCancelRequest());
        self::assertFalse($this->Gateway->isSuccessRequest());

        $this->Gateway->enableGatewayPayment();
        self::assertTrue($this->Gateway->isGatewayPayment());

        $this->Gateway->disableGatewayPayment();
        self::assertFalse($this->Gateway->isGatewayPayment());
    }

    public function testGatewayUrlsPreservePurposeOrderAndCallerParameters(): void
    {
        $_SERVER['HTTP_HOST'] = 'payments.example.test';
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('order-uuid');
        $this->setGatewayProperty('Order', $Order);

        $gatewayUrl = $this->Gateway->getGatewayUrl(['provider' => 'demo']);
        self::assertStringStartsWith('https://payments.example.test', $gatewayUrl);
        self::assertStringContainsString('orderHash=order-uuid', $gatewayUrl);
        self::assertStringContainsString('provider=demo', $gatewayUrl);
        self::assertStringContainsString('success=1', $this->Gateway->getSuccessUrl());
        self::assertStringContainsString('canceled=1', $this->Gateway->getCancelUrl());
        self::assertStringContainsString('error=1', $this->Gateway->getErrorUrl());
        self::assertStringContainsString('GatewayPayment=1', $this->Gateway->getPaymentProviderUrl());
    }

    public function testReadRequestWithoutOrderHashLeavesGatewayUnassigned(): void
    {
        $_REQUEST = ['success' => 1, 'canceled' => 1];

        $this->Gateway->readRequest();

        self::assertNull($this->Gateway->getOrder());
        self::assertFalse($this->Gateway->isSuccessRequest());
        self::assertFalse($this->Gateway->isCancelRequest());
    }

    public function testReadRequestPreservesExplicitlyAssignedOrder(): void
    {
        $Order = $this->createMock(Order::class);
        $this->Gateway->setOrder($Order);
        $_REQUEST['orderHash'] = 'must-not-replace-explicit-order';

        $this->Gateway->readRequest();

        self::assertSame($Order, $this->Gateway->getOrder());
    }

    public function testOrderUrlAndHostFallbacksRemainSafeWithoutOrder(): void
    {
        self::assertSame('', $this->Gateway->getOrderUrl());

        unset($_SERVER['HTTP_HOST']);
        $HostMethod = new ReflectionMethod(Gateway::class, 'getHost');
        $host = $HostMethod->invoke($this->Gateway);

        self::assertIsString($host);
    }

    public function testGatewayHostFallsBackAfterInvalidProjectSelectors(): void
    {
        unset($_SERVER['HTTP_HOST']);
        $HostMethod = new ReflectionMethod(Gateway::class, 'getHost');

        $_REQUEST = ['project' => '{invalid-project-token'];
        self::assertIsString($HostMethod->invoke($this->Gateway));

        $_REQUEST = [
            'project' => 'missing-project',
            'lang' => 'en'
        ];
        self::assertIsString($HostMethod->invoke($this->Gateway));
    }

    public function testOrderProcessFinishesAlreadySuccessfulOrder(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(1);

        self::assertSame(
            AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH,
            (new OrderProcessProvider())->onOrderStart($Order)
        );
    }

    public function testOrderProcessAppendsPaymentStepWithCurrentOrderContext(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('process-order');
        $Process = $this->createMock(OrderProcess::class);
        $Process->method('getOrder')->willReturn($Order);
        $Steps = new OrderProcessSteps();

        (new OrderProcessProvider())->initSteps($Steps, $Process);

        self::assertCount(1, $Steps);
        $Step = $Steps->first();
        self::assertInstanceOf(\QUI\ERP\Accounting\Payments\Order\Payment::class, $Step);
        self::assertSame('process-order', $Step->getAttribute('orderId'));
        self::assertSame($Order, $Step->getAttribute('Order'));
        self::assertSame(30, $Step->getAttribute('priority'));
    }

    public function testOrderProcessRequiresPaymentForUnfinishedOrder(): void
    {
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getPayment')->willReturn(null);

        $this->expectException(PaymentsException::class);
        $this->expectExceptionMessage('No payment is assigned');

        (new OrderProcessProvider())->onOrderStart($Order);
    }

    public function testOrderProcessDistinguishesGatewayFromOfflinePayments(): void
    {
        self::assertSame(
            AbstractOrderProcessProvider::PROCESSING_STATUS_PROCESSING,
            $this->startOrderWithGatewayFlag(true)
        );
        self::assertSame(
            AbstractOrderProcessProvider::PROCESSING_STATUS_FINISH,
            $this->startOrderWithGatewayFlag(false)
        );
    }

    public function testOrderProcessRendersPaymentGatewayDisplay(): void
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('isGateway')->willReturn(true);
        $PaymentType->method('getGatewayDisplay')->willReturn('<form>gateway</form>');
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getPayment')->willReturn($Payment);
        $Provider = new OrderProcessProvider();
        $Provider->onOrderStart($Order);

        self::assertSame('<form>gateway</form>', $Provider->getDisplay($Order));
        self::assertFalse($Provider->hasErrors());
    }

    public function testOrderProcessReturnsEmptyDisplayBeforePaymentInitialization(): void
    {
        $Order = $this->createMock(AbstractOrder::class);

        self::assertSame('', (new OrderProcessProvider())->getDisplay($Order));
    }

    public function testOrderProcessTurnsExpectedProcessingFailureIntoVisibleMessage(): void
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('isGateway')->willReturn(true);
        $PaymentType->method('getGatewayDisplay')
            ->willThrowException(new ProcessingException('Provider needs attention'));
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getPayment')->willReturn($Payment);
        $Provider = new OrderProcessProvider();
        $Provider->onOrderStart($Order);

        $display = $Provider->getDisplay($Order);

        self::assertStringContainsString('Provider needs attention', $display);
        self::assertTrue($Provider->hasErrors());
    }

    public function testOrderProcessHidesUnexpectedGatewayFailureBehindGenericMessage(): void
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('isGateway')->willReturn(true);
        $PaymentType->method('getGatewayDisplay')
            ->willThrowException(new \RuntimeException('secret provider detail'));
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getPayment')->willReturn($Payment);
        $Provider = new OrderProcessProvider();
        $Provider->onOrderStart($Order);

        $display = $Provider->getDisplay($Order);

        self::assertStringContainsString('message-error', $display);
        self::assertStringNotContainsString('secret provider detail', $display);
        self::assertTrue($Provider->hasErrors());
    }

    private function startOrderWithGatewayFlag(bool $isGateway): int
    {
        $PaymentType = $this->createMock(AbstractPayment::class);
        $PaymentType->method('isGateway')->willReturn($isGateway);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('getPaymentType')->willReturn($PaymentType);
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('isSuccessful')->willReturn(0);
        $Order->method('getPayment')->willReturn($Payment);

        return (new OrderProcessProvider())->onOrderStart($Order);
    }

    private function setGatewayProperty(string $property, mixed $value): void
    {
        (new ReflectionProperty(Gateway::class, $property))->setValue($this->Gateway, $value);
    }
}
