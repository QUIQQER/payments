<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Payments\Methods\Standard\Payment as StandardPayment;
use QUI\ERP\Accounting\Payments\Tests\DatabaseEnvironment;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Permission;
use QUI\Update;
use ReflectionProperty;

abstract class SqlitePaymentTestCase extends TestCase
{
    protected Connection $connection;

    private Connection $originalConnection;
    private bool $ownsTestConnection = false;
    private bool $ciTransactionStarted = false;
    private ?User $previousPermissionUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = QUI::getDataBaseConnection();
        $PermissionUser = new ReflectionProperty(Permission::class, 'User');
        $this->previousPermissionUser = $PermissionUser->getValue();

        try {
            if (DatabaseEnvironment::usesCiDatabase()) {
                $this->connection = $this->originalConnection;
                $this->connection->beginTransaction();
                $this->ciTransactionStarted = true;
                $this->clearPayments();
            } else {
                $this->connection = DriverManager::getConnection([
                    'driver' => 'pdo_sqlite',
                    'memory' => true
                ]);
                $this->ownsTestConnection = true;
                $this->setConnection($this->connection);
                Update::importDatabase(dirname(__DIR__, 2) . '/database.xml');
            }

            Permission::setUser(QUI::getUsers()->getSystemUser());
        } catch (\Throwable $Exception) {
            $this->restoreConnection();
            throw $Exception;
        }
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Permission::class, 'User'))->setValue(
            null,
            $this->previousPermissionUser
        );

        $this->restoreConnection();

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function insertPayment(array $attributes = []): int
    {
        $attributes += [
            'active' => 0,
            'payment_type' => StandardPayment::class,
            'icon' => '',
            'date_from' => null,
            'date_until' => null,
            'purchase_quantity_from' => 0,
            'purchase_quantity_until' => 0,
            'purchase_value_from' => null,
            'purchase_value_until' => null,
            'priority' => 0,
            'paymentFee' => null,
            'areas' => null,
            'articles' => null,
            'categories' => null,
            'user_groups' => null,
            'currencies' => null
        ];

        $this->connection->insert($this->paymentTable(), $attributes);

        return (int)$this->connection->lastInsertId();
    }

    protected function loadPayment(int $id): Payment
    {
        return (new Factory())->getChild($id);
    }

    protected function paymentTable(): string
    {
        return QUI::getDBTableName('payments');
    }

    protected function ownsTestConnection(): bool
    {
        return $this->ownsTestConnection;
    }

    private function clearPayments(): void
    {
        $table = QUI\Utils\Doctrine::quoteIdentifier($this->paymentTable());
        $this->connection->executeStatement('DELETE FROM ' . $table);
    }

    private function restoreConnection(): void
    {
        if ($this->ciTransactionStarted && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        $this->setConnection($this->originalConnection);

        if ($this->ownsTestConnection) {
            $this->connection->close();
        }
    }

    private function setConnection(Connection $Connection): void
    {
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);
    }
}
