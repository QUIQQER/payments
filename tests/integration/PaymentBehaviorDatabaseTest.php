<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\ERP\Accounting\Payments\Exception as PaymentsException;
use QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed;
use QUI\ERP\Accounting\Payments\Methods\Free;
use QUI\ERP\Accounting\Payments\Methods\Standard\Payment as StandardPayment;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\RecordingPayment;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ERP\Order\OrderInterface;
use QUI\Interfaces\Users\User;
use ReflectionProperty;

class PaymentBehaviorDatabaseTest extends SqlitePaymentTestCase
{
    /** @var array<string, mixed> */
    private array $originalCurrencyState;
    private mixed $originalAllowedCurrencies;
    private ?QUI\Config $CurrencyConfig;
    private mixed $originalUserRelatedCurrency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCurrencyState = [
            'currencies' => $this->staticProperty(CurrencyHandler::class, 'currencies')->getValue(),
            'Default' => $this->staticProperty(CurrencyHandler::class, 'Default')->getValue(),
            'RuntimeCurrency' => $this->staticProperty(CurrencyHandler::class, 'RuntimeCurrency')->getValue()
        ];
        $this->originalUserRelatedCurrency = $this->staticProperty(
            QUI\ERP\Defaults::class,
            'userRelatedCurrency'
        )->getValue();

        $currencyData = [
            'EUR' => [
                'currency' => 'EUR',
                'rate' => 1,
                'autoupdate' => 0,
                'precision' => 2,
                'type' => CurrencyHandler::CURRENCY_TYPE_DEFAULT,
                'customData' => null
            ],
            'USD' => [
                'currency' => 'USD',
                'rate' => 1.1,
                'autoupdate' => 0,
                'precision' => 2,
                'type' => CurrencyHandler::CURRENCY_TYPE_DEFAULT,
                'customData' => null
            ]
        ];
        $this->staticProperty(CurrencyHandler::class, 'currencies')->setValue(null, $currencyData);
        $this->staticProperty(CurrencyHandler::class, 'Default')->setValue(
            null,
            new Currency($currencyData['EUR'])
        );
        $this->staticProperty(CurrencyHandler::class, 'RuntimeCurrency')->setValue(null, null);
        $this->staticProperty(QUI\ERP\Defaults::class, 'userRelatedCurrency')->setValue(null, false);

        $this->CurrencyConfig = QUI::getPackage('quiqqer/currency')->getConfig();
        $this->originalAllowedCurrencies = $this->CurrencyConfig?->getValue('currency', 'allowedCurrencies');
        $this->CurrencyConfig?->setValue('currency', 'allowedCurrencies', 'EUR,USD');
    }

    protected function tearDown(): void
    {
        if ($this->originalAllowedCurrencies === false || $this->originalAllowedCurrencies === null) {
            $this->CurrencyConfig?->del('currency', 'allowedCurrencies');
        } else {
            $this->CurrencyConfig?->setValue(
                'currency',
                'allowedCurrencies',
                $this->originalAllowedCurrencies
            );
        }

        foreach ($this->originalCurrencyState as $property => $value) {
            $this->staticProperty(CurrencyHandler::class, $property)->setValue(null, $value);
        }
        $this->staticProperty(QUI\ERP\Defaults::class, 'userRelatedCurrency')->setValue(
            null,
            $this->originalUserRelatedCurrency
        );

        parent::tearDown();
    }

    public function testHydratedPaymentExposesStoredStateAndPaymentImplementation(): void
    {
        $id = $this->insertPayment([
            'active' => 1,
            'priority' => 27,
            'icon' => 'not-a-media-url',
            'paymentFee' => 3.5
        ]);
        $Payment = $this->loadPayment($id);
        $data = $Payment->toArray();

        self::assertSame($id, $Payment->getId());
        self::assertTrue($Payment->isActive());
        self::assertSame(StandardPayment::class, $Payment->getAttribute('payment_type'));
        self::assertInstanceOf(StandardPayment::class, $Payment->getPaymentType());
        self::assertTrue($Payment->isSuccessful('offline-payment'));
        self::assertSame(27, $data['priority']);
        self::assertSame(1, $data['active']);
        self::assertSame(md5(StandardPayment::class), $data['paymentType']['name']);
        self::assertStringEndsWith('Rechnung.jpg', $Payment->getIcon());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaymentTypeProvider(): iterable
    {
        yield 'missing class' => ['Tests\\MissingPaymentClass'];
        yield 'class with wrong contract' => [\stdClass::class];
    }

    #[DataProvider('invalidPaymentTypeProvider')]
    public function testInvalidStoredPaymentTypeIsRejected(string $paymentType): void
    {
        $Payment = $this->loadPayment($this->insertPayment([
            'payment_type' => $paymentType
        ]));

        $this->expectException(PaymentsException::class);
        $Payment->getPaymentType();
    }

    public function testUserEligibilityHonoursActivationDatesAndDirectAssignments(): void
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn('eligible-user');
        $User->method('getId')->willReturn(42);
        $User->method('getGroups')->willReturn([]);

        self::assertFalse($this->loadPayment($this->insertPayment())->canUsedBy($User));
        self::assertTrue($this->loadPayment($this->insertPayment(['active' => 1]))->canUsedBy($User));
        self::assertFalse($this->loadPayment($this->insertPayment([
            'active' => 1,
            'date_from' => '+1 day'
        ]))->canUsedBy($User));
        self::assertFalse($this->loadPayment($this->insertPayment([
            'active' => 1,
            'date_until' => '-1 day'
        ]))->canUsedBy($User));
        self::assertTrue($this->loadPayment($this->insertPayment([
            'active' => 1,
            'user_groups' => 'ueligible-user'
        ]))->canUsedBy($User));
        self::assertTrue($this->loadPayment($this->insertPayment([
            'active' => 1,
            'user_groups' => 'u42'
        ]))->canUsedBy($User));
        self::assertFalse($this->loadPayment($this->insertPayment([
            'active' => 1,
            'user_groups' => 'uanother-user'
        ]))->canUsedBy($User));
    }

    public function testUserEligibilityAcceptsAssignedGroupsAndRejectsInvalidType(): void
    {
        $Group = $this->createMock(QUI\Groups\Group::class);
        $Group->method('getUUID')->willReturn('eligible-group');
        $Group->method('getId')->willReturn(73);
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn('different-user');
        $User->method('getId')->willReturn(42);
        $User->method('getGroups')->willReturn([$Group]);

        self::assertTrue($this->loadPayment($this->insertPayment([
            'active' => 1,
            'user_groups' => 'geligible-group'
        ]))->canUsedBy($User));
        self::assertTrue($this->loadPayment($this->insertPayment([
            'active' => 1,
            'user_groups' => 'g73'
        ]))->canUsedBy($User));
        self::assertFalse($this->loadPayment($this->insertPayment([
            'active' => 1,
            'payment_type' => 'Tests\\MissingPaymentType'
        ]))->canUsedBy($User));
    }

    public function testOrderEligibilityHonoursConfiguredCurrencies(): void
    {
        $this->requireOrderPackage();

        $Currency = $this->createMock(Currency::class);
        $Currency->method('getCode')->willReturn('EUR');
        $Order = $this->createMock(OrderInterface::class);
        $Order->method('getCurrency')->willReturn($Currency);

        self::assertTrue($this->loadPayment($this->insertPayment([
            'active' => 1,
            'currencies' => 'EUR,USD'
        ]))->canUsedInOrder($Order));

        $PaymentsConfig = QUI::getPackage('quiqqer/payments')->getConfig();
        $originalSetting = $PaymentsConfig?->getValue('payments', 'listUnsupportedPayment');
        $PaymentsConfig?->setValue('payments', 'listUnsupportedPayment', 0);

        try {
            self::assertFalse($this->loadPayment($this->insertPayment([
                'active' => 1,
                'currencies' => 'GBP,USD'
            ]))->canUsedInOrder($Order));
        } finally {
            if ($originalSetting === false || $originalSetting === null) {
                $PaymentsConfig?->del('payments', 'listUnsupportedPayment');
            } else {
                $PaymentsConfig?->setValue('payments', 'listUnsupportedPayment', $originalSetting);
            }
        }
        self::assertTrue($this->loadPayment($this->insertPayment([
            'active' => 1,
            'currencies' => null
        ]))->canUsedInOrder($Order));
    }

    public function testOrderEligibilityHonoursRuntimePaymentRejectionEvent(): void
    {
        $this->requireOrderPackage();

        $Order = $this->createMock(OrderInterface::class);
        $Payment = $this->loadPayment($this->insertPayment(['active' => 1]));
        $rejectPayment = static function (): void {
            throw new PaymentCanNotBeUsed('Rejected by test boundary');
        };
        QUI::getEvents()->addEvent('onQuiqqerPaymentCanUsedInOrder', $rejectPayment);

        try {
            self::assertFalse($Payment->canUsedInOrder($Order));
        } finally {
            QUI::getEvents()->removeEvent('onQuiqqerPaymentCanUsedInOrder', $rejectPayment);
        }
    }

    public function testUserEligibilityHandlesBothRuntimeRejectionExceptions(): void
    {
        $User = $this->createMock(User::class);
        $Payment = $this->loadPayment($this->insertPayment(['active' => 1]));
        $rejectPayment = static function (): void {
            throw new PaymentCanNotBeUsed('Rejected by test boundary');
        };
        QUI::getEvents()->addEvent('onQuiqqerPaymentCanUsedBy', $rejectPayment);

        try {
            self::assertFalse($Payment->canUsedBy($User));
        } finally {
            QUI::getEvents()->removeEvent('onQuiqqerPaymentCanUsedBy', $rejectPayment);
        }

        $failPaymentCheck = static function (): void {
            throw new QUI\Exception('Eligibility service failed');
        };
        QUI::getEvents()->addEvent('onQuiqqerPaymentCanUsedBy', $failPaymentCheck);

        try {
            self::assertFalse($Payment->canUsedBy($User));
        } finally {
            QUI::getEvents()->removeEvent('onQuiqqerPaymentCanUsedBy', $failPaymentCheck);
        }
    }

    public function testOrderEligibilityHandlesRuntimeServiceFailure(): void
    {
        $this->requireOrderPackage();

        $Order = $this->createMock(OrderInterface::class);
        $Payment = $this->loadPayment($this->insertPayment(['active' => 1]));
        $failPaymentCheck = static function (): void {
            throw new QUI\Exception('Eligibility service failed');
        };
        QUI::getEvents()->addEvent('onPaymentsCanUsedInOrder', $failPaymentCheck);

        try {
            self::assertFalse($Payment->canUsedInOrder($Order));
        } finally {
            QUI::getEvents()->removeEvent('onPaymentsCanUsedInOrder', $failPaymentCheck);
        }
    }

    public function testPaymentFeeMutatorsPreserveNumericMeaning(): void
    {
        $Payment = $this->loadPayment($this->insertPayment());

        self::assertFalse($Payment->hasPaymentFee());
        self::assertSame(0, $Payment->getPaymentFee());
        self::assertSame('', $Payment->getPaymentFeeDisplay());

        $Payment->setPaymentFee('12.50');
        self::assertTrue($Payment->hasPaymentFee());
        self::assertSame(12.5, $Payment->getPaymentFee());
        self::assertSame('', $Payment->getPaymentFeeDisplay());

        $Payment->clearPaymentFee();
        self::assertFalse($Payment->hasPaymentFee());
        self::assertSame(0, $Payment->getPaymentFee());
    }

    public function testPaymentLocaleGettersUseTheProvidedLocale(): void
    {
        $Payment = $this->loadPayment($this->insertPayment());
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->expects(self::exactly(4))
            ->method('get')
            ->willReturnCallback(static function (string $package, string $key): string {
                return $package . ':' . $key;
            });

        self::assertSame("quiqqer/payments:payment.{$Payment->getId()}.title", $Payment->getTitle($Locale));
        self::assertSame(
            "quiqqer/payments:payment.{$Payment->getId()}.description",
            $Payment->getDescription($Locale)
        );
        self::assertSame(
            "quiqqer/payments:payment.{$Payment->getId()}.workingTitle",
            $Payment->getWorkingTitle($Locale)
        );
        self::assertSame(
            "quiqqer/payments:payment.{$Payment->getId()}.paymentFeeTitle",
            $Payment->getPaymentFeeTitle($Locale)
        );
        self::assertNotSame('', $Payment->getDescription());
        self::assertNotSame('', $Payment->getWorkingTitle());
    }

    public function testPaymentLocaleMutatorsTargetTheirDocumentedVariables(): void
    {
        $Payment = new RecordingPayment(52, new Factory());
        $Payment->setTitle(['en' => 'Title']);
        $Payment->setDescription(['en' => 'Description']);
        $Payment->setOrderInformation(['en' => 'Order information']);
        $Payment->setWorkingTitle(['en' => 'Working title']);
        $Payment->setPaymentFeeTitle(['en' => 'Payment fee']);

        self::assertSame([
            'payment.52.title' => ['en' => 'Title'],
            'payment.52.description' => ['en' => 'Description'],
            'payment.52.orderInformation' => ['en' => 'Order information'],
            'payment.52.workingTitle' => ['en' => 'Working title'],
            'payment.52.paymentFeeTitle' => ['en' => 'Payment fee']
        ], $Payment->localeChanges);
    }

    public function testSupportedCurrenciesUseConfiguredAllowedCurrencySet(): void
    {
        $DefaultPayment = $this->loadPayment($this->insertPayment());
        self::assertSame(['EUR'], array_map(
            static fn(Currency $Currency): string => $Currency->getCode(),
            $DefaultPayment->getSupportedCurrencies()
        ));

        $ConfiguredPayment = $this->loadPayment($this->insertPayment([
            'currencies' => 'USD,EUR,UNKNOWN'
        ]));
        self::assertSame(['USD', 'EUR'], array_map(
            static fn(Currency $Currency): string => $Currency->getCode(),
            $ConfiguredPayment->getSupportedCurrencies()
        ));
        self::assertTrue($ConfiguredPayment->isCurrencySupported(
            CurrencyHandler::getCurrency('USD')
        ));
        self::assertFalse($ConfiguredPayment->isCurrencySupported(new Currency([
            'currency' => 'GBP',
            'rate' => 1,
            'autoupdate' => 0,
            'precision' => 2
        ])));

        $FallbackPayment = $this->loadPayment($this->insertPayment([
            'currencies' => 'UNKNOWN'
        ]));
        self::assertSame(['EUR', 'USD'], array_map(
            static fn(Currency $Currency): string => $Currency->getCode(),
            $FallbackPayment->getSupportedCurrencies()
        ));
    }

    public function testPaymentFeeCreatesPriceFactorAndGrossDisplay(): void
    {
        $this->requireOrderPackage();

        $Payment = $this->loadPayment($this->insertPayment(['paymentFee' => 12.5]));
        $Currency = CurrencyHandler::getCurrency('EUR');
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCurrency')->willReturn($Currency);
        $PriceFactor = $Payment->toPriceFactor(null, $Order);

        self::assertSame(12.5, $PriceFactor->getValue());
        self::assertSame('EUR', $PriceFactor->getCurrency()->getCode());

        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('isNetto')->willReturn(true);
        $Calculations = $this->createMock(Calculations::class);
        $Calculations->method('getVat')->willReturn([]);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getPriceCalculation')->willReturn($Calculations);
        $Payment->setAttribute('Order', $Order);

        $display = $Payment->getPaymentFeeDisplay();
        self::assertStringContainsString('<span title="', $display);
        self::assertStringContainsString('12', $display);
    }

    public function testOrderInformationBuildsCompleteTemplatePayload(): void
    {
        $this->requireOrderPackage();

        $Payment = $this->loadPayment($this->insertPayment());
        $Currency = CurrencyHandler::getCurrency('EUR');
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getShipping')->willReturn(null);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getPaidStatusInformation')->willReturn([
            'paidDate' => '2026-08-19',
            'paid' => 10,
            'toPay' => 15.5
        ]);
        $Order->method('getIdPrefix')->willReturn('ORD-');
        $Order->method('getId')->willReturn(4711);
        $originalLocale = QUI::$Locale;
        $Locale = $this->createMock(QUI\Locale::class);
        $Locale->expects(self::once())
            ->method('get')
            ->with(
                'quiqqer/payments',
                'payment.' . $Payment->getId() . '.orderInformation',
                self::callback(static function (array $payload): bool {
                    return $payload['orderId'] === 'ORD-4711'
                        && $payload['paidDate'] === '2026-08-19'
                        && $payload['shipping'] === ''
                        && $payload['paid'] !== ''
                        && $payload['toPay'] !== ''
                        && array_key_exists('bankName', $payload)
                        && array_key_exists('company', $payload);
                })
            )
            ->willReturn('rendered order information');
        QUI::$Locale = $Locale;

        try {
            self::assertSame('rendered order information', $Payment->getOrderInformationText($Order));
        } finally {
            QUI::$Locale = $originalLocale;
        }
    }

    public function testPaymentFeeDisplayMarksHighPrecisionGrossAmountAsApproximate(): void
    {
        $this->requireOrderPackage();

        $Payment = $this->loadPayment($this->insertPayment(['paymentFee' => 1.123456]));
        $Currency = CurrencyHandler::getCurrency('EUR');
        $Customer = $this->createMock(QUI\ERP\User::class);
        $Customer->method('isNetto')->willReturn(false);
        $VatEntry = $this->createMock(QUI\ERP\Accounting\CalculationVatValue::class);
        $VatEntry->method('getVat')->willReturn(19.0);
        $Calculations = $this->createMock(Calculations::class);
        $Calculations->method('getVat')->willReturn([$VatEntry]);
        $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
        $Order->method('getCurrency')->willReturn($Currency);
        $Order->method('getCustomer')->willReturn($Customer);
        $Order->method('getPriceCalculation')->willReturn($Calculations);
        $Payment->setAttribute('Order', $Order);

        $display = $Payment->getPaymentFeeDisplay();

        self::assertStringContainsString('~', $display);
        self::assertStringContainsString('<span title="', $display);
    }

    public function testPaymentsFacadeLoadsFreeStoredAndEligiblePayments(): void
    {
        $activeId = $this->insertPayment(['active' => 1, 'priority' => 20]);
        $this->insertPayment(['active' => 0, 'priority' => 10]);
        $User = $this->createMock(User::class);
        $User->method('getGroups')->willReturn([]);

        $FreePayment = Payments::getInstance()->getPayment(Free\Payment::ID);
        self::assertInstanceOf(Free\PaymentType::class, $FreePayment);
        self::assertSame(Free\Payment::ID, $FreePayment->getId());
        self::assertSame($activeId, Payments::getInstance()->getPayment($activeId)->getId());
        self::assertCount(2, Payments::getInstance()->getPayments());

        $eligible = Payments::getInstance()->getUserPayments($User);
        self::assertCount(1, $eligible);
        self::assertSame($activeId, $eligible[0]->getId());
        self::assertCount(1, Payments::getInstance()->getUserPayments());

        $this->expectException(QUI\Exception::class);
        Payments::getInstance()->getPayment(999999);
    }

    public function testFactoryMetadataAndInvalidCreationInput(): void
    {
        $Factory = new Factory();

        self::assertSame('payments', $Factory->getDataBaseTableName());
        self::assertSame(QUI\ERP\Accounting\Payments\Types\Payment::class, $Factory->getChildClass());
        self::assertContains('payment_type', $Factory->getChildAttributes());
        self::assertContains('currencies', $Factory->getChildAttributes());

        $this->expectException(PaymentsException::class);
        $Factory->createChild(['payment_type' => \stdClass::class]);
    }

    private function staticProperty(string $class, string $property): ReflectionProperty
    {
        return new ReflectionProperty($class, $property);
    }

    private function requireOrderPackage(): void
    {
        if (!interface_exists(OrderInterface::class)) {
            self::markTestSkipped('Optional dependency quiqqer/order is not installed.');
        }
    }
}
