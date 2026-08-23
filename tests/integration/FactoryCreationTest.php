<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI;
use QUI\ERP\Accounting\Payments\Exception as PaymentsException;
use QUI\ERP\Accounting\Payments\Settings;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\InstallPaymentProvider;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\RecordingPaymentFactory;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\TestPaymentMethod;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\TestablePayments;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\UniqueTestPaymentMethod;
use ReflectionProperty;

class FactoryCreationTest extends SqlitePaymentTestCase
{
    private Settings $Settings;
    private ?QUI\Config $originalConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->Settings = Settings::getInstance();
        $ConfigProperty = new ReflectionProperty(Settings::class, 'Config');
        $this->originalConfig = $ConfigProperty->getValue($this->Settings);
        $ConfigProperty->setValue($this->Settings, $this->createMock(QUI\Config::class));
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Settings::class, 'Config'))->setValue(
            $this->Settings,
            $this->originalConfig
        );

        parent::tearDown();
    }

    public function testCreateChildPersistsDefaultsFeeAndLocaleContracts(): void
    {
        $Factory = new RecordingPaymentFactory();
        $Payment = $Factory->createChild([
            'payment_type' => TestPaymentMethod::class,
            'paymentFee' => '4.50',
            'active' => 1,
            'priority' => 12
        ]);
        $stored = $this->connection->fetchAssociative(
            'SELECT * FROM ' . $this->paymentTable() . ' WHERE id = ?',
            [$Payment->getId()]
        );

        self::assertIsArray($stored);
        self::assertSame(TestPaymentMethod::class, $stored['payment_type']);
        self::assertSame(1, (int)$stored['active']);
        self::assertSame(0, (int)$stored['purchase_quantity_from']);
        self::assertSame(0, (int)$stored['purchase_quantity_until']);
        self::assertSame(12, (int)$stored['priority']);
        self::assertEqualsWithDelta(4.5, (float)$stored['paymentFee'], 0.00001);
        self::assertSame([
            'payment.' . $Payment->getId() . '.title',
            'payment.' . $Payment->getId() . '.workingTitle',
            'payment.' . $Payment->getId() . '.description',
            'payment.' . $Payment->getId() . '.orderInformation'
        ], array_keys($Factory->createdLocales));

        $description = $Factory->createdLocales['payment.' . $Payment->getId() . '.description'];
        self::assertIsArray($description);
        self::assertNotEmpty($description);

        foreach ($description as $translatedDescription) {
            self::assertSame('Test description', $translatedDescription);
        }
    }

    public function testUniquePaymentTypeCannotBeCreatedTwice(): void
    {
        $this->insertPayment([
            'payment_type' => UniqueTestPaymentMethod::class
        ]);
        $Factory = new RecordingPaymentFactory();

        $this->expectException(PaymentsException::class);
        $Factory->createChild([
            'payment_type' => UniqueTestPaymentMethod::class
        ]);
    }

    public function testCreatePaymentsOnInstallCreatesDeclaredTypesDisabledAndOnlyOnce(): void
    {
        $Factory = new RecordingPaymentFactory();
        $Payments = new TestablePayments($Factory);
        $Provider = new InstallPaymentProvider();

        $createdPayments = $Payments->createPaymentsOnInstall($Provider);

        self::assertCount(2, $createdPayments);
        self::assertSame(2, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->paymentTable()
        ));
        self::assertSame(0, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->paymentTable() . ' WHERE active = 1'
        ));

        $configuredPayment = $this->connection->fetchAssociative(
            'SELECT active, priority, paymentFee FROM ' . $this->paymentTable() . ' WHERE id = ?',
            [$createdPayments[0]->getId()]
        );

        self::assertIsArray($configuredPayment);
        self::assertSame(0, (int)$configuredPayment['active']);
        self::assertSame(17, (int)$configuredPayment['priority']);
        self::assertEqualsWithDelta(2.5, (float)$configuredPayment['paymentFee'], 0.00001);
        self::assertSame(
            ['de' => 'Installierter Testtitel', 'en' => 'Installed test title'],
            $Factory->createdLocales['payment.' . $createdPayments[0]->getId() . '.title']
        );
        self::assertSame(
            '[quiqqer/payment-test] payment.workingTitle',
            $Factory->createdLocales['payment.' . $createdPayments[0]->getId() . '.workingTitle']
        );
        self::assertSame(
            '[quiqqer/payment-test] payment.description',
            $Factory->createdLocales['payment.' . $createdPayments[0]->getId() . '.description']
        );
        self::assertSame(
            ['de' => 'Bestellinformation', 'en' => 'Order information'],
            $Factory->createdLocales['payment.' . $createdPayments[0]->getId() . '.orderInformation']
        );

        self::assertSame([], $Payments->createPaymentsOnInstall($Provider));
        self::assertSame(2, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->paymentTable()
        ));
    }

    public function testCreatePaymentsOnInstallLeavesExistingPaymentsUntouched(): void
    {
        $existingId = $this->insertPayment([
            'payment_type' => TestPaymentMethod::class,
            'active' => 1,
            'priority' => 42
        ]);

        $createdPayments = (new TestablePayments(new RecordingPaymentFactory()))->createPaymentsOnInstall(
            new InstallPaymentProvider()
        );

        self::assertCount(1, $createdPayments);

        $existingPayment = $this->connection->fetchAssociative(
            'SELECT active, priority FROM ' . $this->paymentTable() . ' WHERE id = ?',
            [$existingId]
        );

        self::assertIsArray($existingPayment);
        self::assertSame(1, (int)$existingPayment['active']);
        self::assertSame(42, (int)$existingPayment['priority']);
    }
}
