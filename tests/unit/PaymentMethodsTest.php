<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\InvoiceView;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Gateway\Gateway;
use QUI\ERP\Accounting\Payments\Methods\AdvancePayment;
use QUI\ERP\Accounting\Payments\Methods\Cash;
use QUI\ERP\Accounting\Payments\Methods\CashOnDelivery;
use QUI\ERP\Accounting\Payments\Methods\Free;
use QUI\ERP\Accounting\Payments\Methods\Invoice as InvoicePayment;
use QUI\ERP\Accounting\Payments\Methods\Standard;
use QUI\ERP\Accounting\Payments\Provider;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\TestPaymentMethod;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Enums\Payments\EN16931;
use QUI\ERP\Order\AbstractOrder;

class PaymentMethodsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractPayment>, EN16931, bool, bool, string}>
     */
    public static function paymentMethodProvider(): iterable
    {
        yield 'advance payment' => [
            AdvancePayment\Payment::class,
            EN16931::DEBIT_CARD,
            false,
            false,
            'Vorkasse.png'
        ];
        yield 'cash' => [Cash\Payment::class, EN16931::CASH, false, true, 'Bar.jpg'];
        yield 'cash on delivery' => [CashOnDelivery\Payment::class, EN16931::CASH, false, true, 'Bar.jpg'];
        yield 'invoice' => [
            InvoicePayment\Payment::class,
            EN16931::CREDIT_TRANSFER,
            false,
            false,
            'Rechnung.jpg'
        ];
        yield 'standard' => [
            Standard\Payment::class,
            EN16931::CREDIT_TRANSFER,
            false,
            false,
            'Rechnung.jpg'
        ];
    }

    /**
     * @return iterable<string, array{class-string<AbstractPayment>, bool}>
     */
    public static function paymentApprovalProvider(): iterable
    {
        yield 'advance payment' => [AdvancePayment\Payment::class, false];
        yield 'cash' => [Cash\Payment::class, true];
        yield 'cash on delivery' => [CashOnDelivery\Payment::class, true];
        yield 'invoice' => [InvoicePayment\Payment::class, true];
        yield 'standard' => [Standard\Payment::class, true];
    }

    #[DataProvider('paymentMethodProvider')]
    public function testBuiltInPaymentMethodsExposeTheirBusinessCapabilities(
        string $paymentClass,
        EN16931 $expectedTypeCode,
        bool $expectedGateway,
        bool $expectedRefundSupport,
        string $iconSuffix
    ): void {
        $Payment = new $paymentClass();
        $data = $Payment->toArray();

        self::assertSame($paymentClass, $Payment->getClass());
        self::assertSame(md5($paymentClass), $Payment->getName());
        self::assertSame($expectedTypeCode, $Payment->getTypeCode());
        self::assertSame($expectedGateway, $Payment->isGateway());
        self::assertSame($expectedRefundSupport, $Payment->refundSupport());
        self::assertTrue($Payment->isSuccessful('irrelevant-for-offline-methods'));
        self::assertStringEndsWith($iconSuffix, $Payment->getIcon());
        self::assertSame($Payment->getTitle(), $data['title']);
        self::assertSame($Payment->getDescription(), $data['description']);
        self::assertSame($expectedTypeCode, $data['typeCode']);
    }

    #[DataProvider('paymentApprovalProvider')]
    public function testBuiltInPaymentApprovalMatchesOfflineSemantics(
        string $paymentClass,
        bool $expectedApproval
    ): void {
        if ($paymentClass === AdvancePayment\Payment::class) {
            $this->requireOrderPackage();
        }

        self::assertSame(
            $expectedApproval,
            (new $paymentClass())->isApproved('irrelevant-for-offline-methods')
        );
    }

    public function testProviderAdvertisesAllStandardPaymentImplementations(): void
    {
        self::assertSame([
            Cash\Payment::class,
            InvoicePayment\Payment::class,
            AdvancePayment\Payment::class,
            Standard\Payment::class
        ], (new Provider())->getPaymentTypes());
    }

    public function testAbstractPaymentDefaultsFormAStableExtensionContract(): void
    {
        $this->requireOrderPackage();

        $Payment = new TestPaymentMethod();
        $Order = $this->createMock(AbstractOrder::class);

        self::assertSame(EN16931::NOT_DEFINED, $Payment->getTypeCode());
        self::assertTrue($Payment->isApproved('successful'));
        self::assertFalse($Payment->isApproved('failed'));
        self::assertTrue($Payment->isVisible($Order));
        self::assertFalse($Payment->isUnique());
        self::assertFalse($Payment->isGateway());
        self::assertFalse($Payment->refundSupport());
        self::assertFalse($Payment->supportsRecurringPayments());
        self::assertFalse($Payment->supportsRecurringPaymentsOnly());
        self::assertSame('', $Payment->getGatewayDisplay($Order));
        self::assertStringEndsWith('/quiqqer/payments/bin/payments/default.png', $Payment->getIcon());
        self::assertSame('Test title', $Payment->toArray()['title']);

        $Locale = clone QUI::getLocale();
        $Payment->setLocale($Locale);
        self::assertSame($Locale, $Payment->getLocale());
    }

    public function testRecurringOfflineMethodsUseNoExternalSubscriptionState(): void
    {
        $this->requireOrderPackage();
        $this->requireInvoicePackage();

        $Order = $this->createMock(AbstractOrder::class);
        $Invoice = $this->createMock(Invoice::class);

        foreach ([new Standard\Payment(), new InvoicePayment\Payment()] as $Payment) {
            self::assertTrue($Payment->supportsRecurringPayments());
            self::assertNull($Payment->createSubscription($Order));

            $Payment->captureSubscription($Invoice);
            $Payment->cancelSubscription('subscription', 'customer request');
            $Payment->suspendSubscription('subscription', 'awaiting review');
            $Payment->resumeSubscription('subscription', 'review complete');
            $Payment->setSubscriptionAsInactive('subscription');

            self::assertFalse($Payment->isSuspended('subscription'));
            self::assertTrue($Payment->isSubscriptionEditable());
            self::assertFalse($Payment->getSubscriptionIdByOrder($Order));
            self::assertTrue($Payment->isSubscriptionActiveAtPaymentProvider('subscription'));
            self::assertTrue($Payment->isSubscriptionActiveAtQuiqqer('subscription'));
            self::assertSame([], $Payment->getSubscriptionIds(true));
            self::assertFalse($Payment->getSubscriptionGlobalProcessingId('subscription'));
        }
    }

    public function testFreePaymentTypeExposesACompleteAlwaysUsableSelection(): void
    {
        $this->requireOrderPackage();

        $Payment = new Free\PaymentType(Free\Payment::ID, new Factory());
        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $Order = $this->createMock(QUI\ERP\Order\OrderInterface::class);
        $data = $Payment->toArray();

        self::assertSame(Free\Payment::ID, $Payment->getId());
        self::assertSame(Free\Payment::ID, $data['payment_code']);
        self::assertSame(Free\PaymentType::class, $data['payment_type']);
        self::assertSame($Payment->getTitle(), $data['title']);
        self::assertSame($Payment->getWorkingTitle(), $data['workingTitle']);
        self::assertSame($Payment->getDescription(), $data['description']);
        self::assertSame($Payment->getPaymentType()->getIcon(), $Payment->getIcon());
        self::assertTrue($Payment->isSuccessful('any-order'));
        self::assertTrue($Payment->canUsedBy($User));
        self::assertFalse($Payment->hasPaymentFee());
        self::assertSame('', $Payment->getOrderInformationText($Order));
    }

    public function testFreePaymentRejectsUnknownOrNonFreeOrders(): void
    {
        $this->requireOrderPackage();

        $Payment = new Free\Payment();

        self::assertFalse($Payment->isSuccessful('non-existent-order'));
        self::assertFalse($Payment->refundSupport());
        self::assertSame(EN16931::MUTUALLY_DEFINED, $Payment->getTypeCode());
        self::assertNotSame('', $Payment->getWorkingTitle());
    }

    public function testDefaultGatewayHooksReturnNoPresentationOrInvoiceText(): void
    {
        $this->requireOrderPackage();
        $this->requireInvoicePackage();

        $Payment = new TestPaymentMethod();
        $Order = $this->createMock(AbstractOrder::class);
        $Invoice = $this->createMock(Invoice::class);

        $Payment->executeGatewayPayment(Gateway::getInstance());

        self::assertSame('', $Payment->getGatewayDisplay($Order));
        self::assertSame('', $Payment->getInvoiceInformationText($Invoice));
    }

    public function testInvoicePaymentSelectsPaidImmediateAndDatedInformation(): void
    {
        $this->requireInvoicePackage();

        $Payment = new InvoicePayment\Payment();
        $PaidInvoice = $this->createMock(Invoice::class);
        $PaidInvoice->method('isPaid')->willReturn(true);
        self::assertSame(
            QUI::getLocale()->get(
                'quiqqer/payments',
                'text.invoice.information.for.invoicePayment.paid'
            ),
            $Payment->getInvoiceInformationText($PaidInvoice)
        );

        $ImmediateInvoice = $this->createMock(InvoiceTemporary::class);
        $ImmediateInvoice->method('isPaid')->willReturn(false);
        $ImmediateInvoice->method('getAttribute')->with('time_for_payment')->willReturn(0);
        self::assertSame(
            QUI::getLocale()->get(
                'quiqqer/payments',
                'text.invoice.information.for.invoicePayment.pay.now'
            ),
            $Payment->getInvoiceInformationText($ImmediateInvoice)
        );

        $FutureInvoice = $this->createMock(InvoiceTemporary::class);
        $FutureInvoice->method('isPaid')->willReturn(false);
        $FutureInvoice->method('getAttribute')->with('time_for_payment')->willReturn(5);
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('getLocale')->willReturn(QUI::getLocale());
        $FutureInvoice->method('getCustomer')->willReturn($Customer);
        $futureText = $Payment->getInvoiceInformationText($FutureInvoice);
        self::assertNotSame('', $futureText);
        self::assertNotSame(
            QUI::getLocale()->get(
                'quiqqer/payments',
                'text.invoice.information.for.invoicePayment.pay.now'
            ),
            $futureText
        );

        $DatedInvoice = $this->createMock(Invoice::class);
        $DatedInvoice->method('isPaid')->willReturn(false);
        $DatedInvoice->method('getAttribute')->with('time_for_payment')->willReturn('+7 days');
        $DatedInvoice->method('getCustomer')->willReturn($Customer);
        $InvoiceView = $this->createMock(InvoiceView::class);
        $InvoiceView->method('isPaid')->willReturn(false);
        $InvoiceView->method('getInvoice')->willReturn($DatedInvoice);

        self::assertNotSame('', $Payment->getInvoiceInformationText($InvoiceView));
    }

    public function testPaymentsFacadeDiscoversProviderTypesAndHost(): void
    {
        $Payments = Payments::getInstance();
        $providers = $Payments->getPaymentProviders();
        $types = $Payments->getPaymentTypes();

        self::assertContainsOnlyInstancesOf(
            QUI\ERP\Accounting\Payments\Api\AbstractPaymentProvider::class,
            $providers
        );
        self::assertArrayHasKey(md5(Standard\Payment::class), $types);
        self::assertInstanceOf(
            Standard\Payment::class,
            $Payments->getPaymentType(md5(Standard\Payment::class))
        );
        self::assertSame(trim($Payments->getHost(), '/'), $Payments->getHost());
    }

    public function testPaymentsFacadeRejectsUnknownPaymentTypeHash(): void
    {
        $this->expectException(QUI\Exception::class);

        Payments::getInstance()->getPaymentType('missing-payment-type');
    }

    public function testPaymentProviderDiscoveryRebuildsInvalidatedCache(): void
    {
        $cacheKey = 'package/quiqqer/payments/provider';
        $hadCachedValue = true;

        try {
            $cachedValue = QUI\Cache\Manager::get($cacheKey);
        } catch (QUI\Cache\Exception) {
            $hadCachedValue = false;
            $cachedValue = null;
        }

        QUI\Cache\Manager::clear($cacheKey);

        try {
            $providers = Payments::getInstance()->getPaymentProviders();
            self::assertContainsOnlyInstancesOf(
                QUI\ERP\Accounting\Payments\Api\AbstractPaymentProvider::class,
                $providers
            );
            self::assertContains(Provider::class, array_map(
                static fn($Provider): string => $Provider::class,
                $providers
            ));
        } finally {
            if ($hadCachedValue) {
                QUI\Cache\Manager::set($cacheKey, $cachedValue);
            } else {
                QUI\Cache\Manager::clear($cacheKey);
            }
        }
    }

    private function requireOrderPackage(): void
    {
        if (!class_exists(AbstractOrder::class)) {
            self::markTestSkipped('Optional dependency quiqqer/order is not installed.');
        }
    }

    private function requireInvoicePackage(): void
    {
        if (!class_exists(Invoice::class)) {
            self::markTestSkipped('Optional dependency quiqqer/invoice is not installed.');
        }
    }
}
