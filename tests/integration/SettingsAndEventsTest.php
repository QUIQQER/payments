<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use PHPUnit\Framework\MockObject\MockObject;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Payments\EventHandling;
use QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed;
use QUI\ERP\Accounting\Payments\Methods\AdvancePayment;
use QUI\ERP\Accounting\Payments\Methods\Cash;
use QUI\ERP\Accounting\Payments\Methods\Invoice;
use QUI\ERP\Accounting\Payments\Methods\Standard;
use QUI\ERP\Accounting\Payments\Settings;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\RecurringOnlyPaymentMethod;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Products\Product\ProductList;
use QUI\ERP\Products\Utils\PriceFactor;
use QUI\ERP\Products\Utils\PriceFactors;
use QUI\Package\Package;
use ReflectionProperty;

class SettingsAndEventsTest extends SqlitePaymentTestCase
{
    private Settings $Settings;
    private ?QUI\Config $originalConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->Settings = Settings::getInstance();
        $ConfigProperty = new ReflectionProperty(Settings::class, 'Config');
        $this->originalConfig = $ConfigProperty->getValue($this->Settings);
        $ConfigProperty->setValue($this->Settings, null);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Settings::class, 'Config'))->setValue(
            $this->Settings,
            $this->originalConfig
        );

        parent::tearDown();
    }

    public function testSettingsDelegateReadWriteSaveAndRemovalToPackageConfig(): void
    {
        $Config = $this->createMock(QUI\Config::class);
        $Config->expects(self::once())->method('get')->with('payments', '42')->willReturn('1');
        $Config->expects(self::once())->method('setValue')->with('payments', '42', 1);
        $Config->expects(self::once())->method('del')->with('obsolete');
        $Config->expects(self::once())->method('save');
        $this->injectConfig($Config);

        self::assertSame('1', $this->Settings->get('payments', '42'));
        $this->Settings->set('payments', '42', 1);
        $this->Settings->removeSection('obsolete');
        $this->Settings->save();
    }

    public function testSettingsLazilyLoadsPackageConfigurationForReads(): void
    {
        $Config = QUI::getPackage('quiqqer/payments')->getConfig();
        self::assertNotNull($Config);

        self::assertSame(
            $Config->get('payments', 'listUnsupportedPayment'),
            $this->Settings->get('payments', 'listUnsupportedPayment')
        );
    }

    public function testSettingsConvertConfigAccessFailuresToSafeResults(): void
    {
        $Config = $this->createMock(QUI\Config::class);
        $Config->method('get')->willThrowException(new QUI\Exception('read failed'));
        $Config->expects(self::once())
            ->method('setValue')
            ->willThrowException(new QUI\Exception('write failed'));
        $Config->expects(self::once())
            ->method('del')
            ->willThrowException(new QUI\Exception('delete failed'));
        $this->injectConfig($Config);

        self::assertFalse($this->Settings->get('payments', 'missing'));
        $this->Settings->set('payments', 'missing', 1);
        $this->Settings->removeSection('payments');
    }

    public function testSettingsSavePropagatesConfigFailure(): void
    {
        $Config = $this->createMock(QUI\Config::class);
        $Config->method('save')->willThrowException(new QUI\Exception('save failed'));
        $this->injectConfig($Config);

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionMessage('save failed');
        $this->Settings->save();
    }

    public function testConfigSaveForOtherPackageLeavesPaymentSettingsUntouched(): void
    {
        $Config = $this->createMock(QUI\Config::class);
        $Config->expects(self::never())->method('setValue');
        $Config->expects(self::never())->method('save');
        $this->injectConfig($Config);
        $Package = $this->createMock(Package::class);
        $Package->method('getName')->willReturn('quiqqer/another-package');

        EventHandling::onPackageConfigSave($Package, [
            'payments' => ['paymentsJson' => '{"1":true}']
        ]);
    }

    public function testConfigSaveWithoutPaymentPayloadIsIgnored(): void
    {
        $Config = $this->createMock(QUI\Config::class);
        $Config->expects(self::never())->method('save');
        $this->injectConfig($Config);

        EventHandling::onPackageConfigSave($this->paymentsPackage(), []);
        EventHandling::onPackageConfigSave($this->paymentsPackage(), ['payments' => []]);
    }

    public function testEmptyPaymentConfigurationRemovesAndSavesSection(): void
    {
        $Config = $this->createMock(QUI\Config::class);
        $Config->expects(self::once())->method('del')->with('payments');
        $Config->expects(self::once())->method('save');
        $this->injectConfig($Config);
        $Package = $this->paymentsPackage();

        EventHandling::onPackageConfigSave($Package, [
            'payments' => ['paymentsJson' => '']
        ]);
    }

    public function testEmptyPaymentConfigurationToleratesPersistenceFailure(): void
    {
        $Config = $this->createMock(QUI\Config::class);
        $Config->expects(self::once())->method('del')->with('payments');
        $Config->expects(self::once())
            ->method('save')
            ->willThrowException(new QUI\Exception('save failed'));
        $this->injectConfig($Config);

        EventHandling::onPackageConfigSave($this->paymentsPackage(), [
            'payments' => ['paymentsJson' => '']
        ]);
    }

    public function testPaymentConfigurationPersistsOnlyExistingPaymentIds(): void
    {
        $paymentId = $this->insertPayment(['active' => 1]);
        $Config = $this->createMock(QUI\Config::class);
        $Config->expects(self::once())
            ->method('setValue')
            ->with('payments', (string)$paymentId, 1);
        $Config->expects(self::once())->method('save');
        $this->injectConfig($Config);

        EventHandling::onPackageConfigSave($this->paymentsPackage(), [
            'payments' => [
                'paymentsJson' => json_encode([
                    (string)$paymentId => true,
                    '999999' => false
                ], JSON_THROW_ON_ERROR)
            ]
        ]);
    }

    public function testPaymentConfigurationToleratesPersistenceFailure(): void
    {
        $paymentId = $this->insertPayment(['active' => 1]);
        $Config = $this->createMock(QUI\Config::class);
        $Config->expects(self::once())
            ->method('setValue')
            ->with('payments', (string)$paymentId, 1);
        $Config->expects(self::once())
            ->method('save')
            ->willThrowException(new QUI\Exception('save failed'));
        $this->injectConfig($Config);

        EventHandling::onPackageConfigSave($this->paymentsPackage(), [
            'payments' => [
                'paymentsJson' => json_encode([(string)$paymentId => true], JSON_THROW_ON_ERROR)
            ]
        ]);
    }

    public function testBasketConversionAddsPaymentFeeAndRecalculatesTotals(): void
    {
        $this->requireOrderPackage();

        $PriceFactor = $this->createMock(PriceFactor::class);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('hasPaymentFee')->willReturn(true);
        $Payment->expects(self::once())
            ->method('toPriceFactor')
            ->willReturn($PriceFactor);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->expects(self::once())->method('calc');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->method('getArticles')->willReturn($Articles);
        $PriceFactors = $this->createMock(PriceFactors::class);
        $PriceFactors->expects(self::once())->method('addToEnd')->with($PriceFactor);
        $Products = $this->createMock(ProductList::class);
        $Products->method('getPriceFactors')->willReturn($PriceFactors);
        $Products->expects(self::once())->method('recalculation')->willReturnSelf();

        EventHandling::onQuiqqerOrderBasketToOrderEnd(
            $this->createMock(QUI\ERP\Order\Basket\Basket::class),
            $Order,
            $Products
        );
    }

    public function testBasketConversionWithoutPaymentOrFeeHasNoSideEffects(): void
    {
        $this->requireOrderPackage();

        $Products = $this->createMock(ProductList::class);
        $Products->expects(self::never())->method('recalculation');
        $OrderWithoutPayment = $this->createMock(AbstractOrder::class);
        $OrderWithoutPayment->method('getPayment')->willReturn(null);

        EventHandling::onQuiqqerOrderBasketToOrderEnd(
            $this->createMock(QUI\ERP\Order\Basket\Basket::class),
            $OrderWithoutPayment,
            $Products
        );

        $Payment = $this->createMock(Payment::class);
        $Payment->method('hasPaymentFee')->willReturn(false);
        $OrderWithoutFee = $this->createMock(AbstractOrder::class);
        $OrderWithoutFee->method('getPayment')->willReturn($Payment);

        EventHandling::onQuiqqerOrderBasketToOrderEnd(
            $this->createMock(QUI\ERP\Order\Basket\Basket::class),
            $OrderWithoutFee,
            $Products
        );
    }

    public function testBasketConversionContinuesAfterProductRecalculationFailure(): void
    {
        $this->requireOrderPackage();

        $PriceFactor = $this->createMock(PriceFactor::class);
        $Payment = $this->createMock(Payment::class);
        $Payment->method('hasPaymentFee')->willReturn(true);
        $Payment->method('toPriceFactor')->willReturn($PriceFactor);
        $Articles = $this->createMock(ArticleList::class);
        $Articles->expects(self::once())->method('calc');
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getPayment')->willReturn($Payment);
        $Order->method('getArticles')->willReturn($Articles);
        $PriceFactors = $this->createMock(PriceFactors::class);
        $PriceFactors->expects(self::once())->method('addToEnd')->with($PriceFactor);
        $Products = $this->createMock(ProductList::class);
        $Products->method('getPriceFactors')->willReturn($PriceFactors);
        $Products->expects(self::once())
            ->method('recalculation')
            ->willThrowException(new QUI\Exception('recalculation failed'));

        EventHandling::onQuiqqerOrderBasketToOrderEnd(
            $this->createMock(QUI\ERP\Order\Basket\Basket::class),
            $Order,
            $Products
        );
    }

    public function testRecurringOnlyPaymentIsRejectedWithoutPlansPackageDecisionMaker(): void
    {
        $this->requireOrderPackage();

        $originalPackageManager = QUI::$PackageManager;
        $PackageManager = $this->createMock(QUI\Package\Manager::class);
        $PackageManager->method('isInstalled')->with('quiqqer/erp-plans')->willReturn(false);
        QUI::$PackageManager = $PackageManager;
        $PaymentType = new RecurringOnlyPaymentMethod();
        $Payment = $this->createMock(Payment::class);
        $Payment->expects(self::once())->method('getPaymentType')->willReturn($PaymentType);
        $Order = $this->createMock(QUI\ERP\Order\OrderInterface::class);

        try {
            $this->expectException(PaymentCanNotBeUsed::class);
            EventHandling::onPaymentsCanUsedInOrder($Payment, $Order);
        } finally {
            QUI::$PackageManager = $originalPackageManager;
        }
    }

    public function testRecurringPaymentCheckToleratesInvalidPaymentImplementation(): void
    {
        $this->requireOrderPackage();

        $originalPackageManager = QUI::$PackageManager;
        $PackageManager = $this->createMock(QUI\Package\Manager::class);
        $PackageManager->method('isInstalled')->with('quiqqer/erp-plans')->willReturn(false);
        QUI::$PackageManager = $PackageManager;
        $Payment = $this->createMock(Payment::class);
        $Payment->expects(self::once())
            ->method('getPaymentType')
            ->willThrowException(new QUI\Exception('invalid payment implementation'));

        try {
            EventHandling::onPaymentsCanUsedInOrder(
                $Payment,
                $this->createMock(QUI\ERP\Order\OrderInterface::class)
            );
        } finally {
            QUI::$PackageManager = $originalPackageManager;
        }
    }

    public function testRecurringPaymentCheckDefersToInstalledPlansPackage(): void
    {
        $this->requireOrderPackage();

        $originalPackageManager = QUI::$PackageManager;
        $PackageManager = $this->createMock(QUI\Package\Manager::class);
        $PackageManager->method('isInstalled')->with('quiqqer/erp-plans')->willReturn(true);
        QUI::$PackageManager = $PackageManager;
        $Payment = $this->createMock(Payment::class);
        $Payment->expects(self::never())->method('getPaymentType');

        try {
            EventHandling::onPaymentsCanUsedInOrder(
                $Payment,
                $this->createMock(QUI\ERP\Order\OrderInterface::class)
            );
        } finally {
            QUI::$PackageManager = $originalPackageManager;
        }
    }

    public function testRecurringPaymentCheckAcceptsNonRecurringPayment(): void
    {
        $this->requireOrderPackage();

        $originalPackageManager = QUI::$PackageManager;
        $PackageManager = $this->createMock(QUI\Package\Manager::class);
        $PackageManager->method('isInstalled')->with('quiqqer/erp-plans')->willReturn(false);
        QUI::$PackageManager = $PackageManager;
        $Payment = $this->createMock(Payment::class);
        $Payment->expects(self::once())
            ->method('getPaymentType')
            ->willReturn(new Standard\Payment());

        try {
            EventHandling::onPaymentsCanUsedInOrder(
                $Payment,
                $this->createMock(QUI\ERP\Order\OrderInterface::class)
            );
        } finally {
            QUI::$PackageManager = $originalPackageManager;
        }
    }

    public function testUpdateEventInvalidatesPaymentProviderCache(): void
    {
        $cacheKey = 'package/quiqqer/payments/provider';
        $hadCachedValue = true;

        try {
            $cachedValue = QUI\Cache\Manager::get($cacheKey);
        } catch (QUI\Cache\Exception) {
            $hadCachedValue = false;
            $cachedValue = null;
        }

        QUI\Cache\Manager::set($cacheKey, ['stale-provider']);

        try {
            EventHandling::onUpdateEnd();

            $this->expectException(QUI\Cache\Exception::class);
            QUI\Cache\Manager::get($cacheKey);
        } finally {
            if ($hadCachedValue) {
                QUI\Cache\Manager::set($cacheKey, $cachedValue);
            } else {
                QUI\Cache\Manager::clear($cacheKey);
            }
        }
    }

    public function testInstallEventIgnoresOtherPackages(): void
    {
        $Package = $this->createMock(Package::class);
        $Package->method('getName')->willReturn('quiqqer/another-package');

        EventHandling::onPackageInstallAfter($Package);

        self::assertSame(0, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->paymentTable()
        ));
    }

    public function testInstallEventLeavesExistingStandardPaymentsUntouched(): void
    {
        $this->insertPayment(['payment_type' => AdvancePayment\Payment::class]);
        $this->insertPayment(['payment_type' => Cash\Payment::class]);
        $this->insertPayment(['payment_type' => Invoice\Payment::class]);

        EventHandling::onPackageInstallAfter($this->paymentsPackage());

        self::assertSame(3, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->paymentTable()
        ));
    }

    public function testErrorHeaderHandlerIgnoresUnrelatedResponses(): void
    {
        $originalRequest = $_REQUEST;

        try {
            $_REQUEST = [];
            EventHandling::onErrorHeaderShow(500, 'PaymentsGateway');
            EventHandling::onErrorHeaderShow(404, 'PaymentsGateway');
            $_REQUEST['_url'] = 'another-route';
            EventHandling::onErrorHeaderShow(404, 'another-route');
        } finally {
            $_REQUEST = $originalRequest;
        }

        self::assertSame($originalRequest, $_REQUEST);
    }

    private function paymentsPackage(): Package&MockObject
    {
        $Package = $this->createMock(Package::class);
        $Package->method('getName')->willReturn('quiqqer/payments');

        return $Package;
    }

    private function injectConfig(QUI\Config $Config): void
    {
        (new ReflectionProperty(Settings::class, 'Config'))->setValue($this->Settings, $Config);
    }

    private function requireOrderPackage(): void
    {
        if (!interface_exists(QUI\ERP\Order\OrderInterface::class)) {
            self::markTestSkipped('Optional dependency quiqqer/order is not installed.');
        }
    }
}
