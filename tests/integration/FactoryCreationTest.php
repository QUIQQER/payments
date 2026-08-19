<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI;
use QUI\ERP\Accounting\Payments\Exception as PaymentsException;
use QUI\ERP\Accounting\Payments\Settings;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\RecordingPaymentFactory;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\TestPaymentMethod;
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
}
