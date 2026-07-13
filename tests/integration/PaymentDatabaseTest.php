<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Payments\Methods\Standard\Payment as StandardPayment;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Permission;
use ReflectionProperty;
use Throwable;

class PaymentDatabaseTest extends TestCase
{
    private const PREFIX = 'phpunit_payments_';

    private ?User $previousPermissionUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->connection()
                ->createQueryBuilder()
                ->select('id')
                ->from($this->table())
                ->setMaxResults(1)
                ->executeQuery()
                ->free();
        } catch (Throwable $Throwable) {
            self::markTestSkipped('Payments table is not available: ' . $Throwable->getMessage());
        }

        $PermissionUser = new ReflectionProperty(Permission::class, 'User');
        $PermissionUser->setAccessible(true);
        $this->previousPermissionUser = $PermissionUser->getValue();
        Permission::setUser(QUI::getUsers()->getSystemUser());

        $this->cleanupFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();

        $PermissionUser = new ReflectionProperty(Permission::class, 'User');
        $PermissionUser->setAccessible(true);
        $PermissionUser->setValue(null, $this->previousPermissionUser);

        parent::tearDown();
    }

    public function testPaymentCanBeLoadedActivatedDeactivatedAndDeleted(): void
    {
        $id = $this->insertFixture('lifecycle', 20);
        $Payment = Factory::getInstance()->getChild($id);

        self::assertSame(StandardPayment::class, $Payment->getAttribute('payment_type'));
        self::assertFalse($Payment->isActive());

        $Payment->activate();
        self::assertTrue($Payment->isActive());

        $Payment->deactivate();
        self::assertFalse($Payment->isActive());

        $Payment->delete();
        self::assertSame(0, $this->countFixture('lifecycle'));
    }

    public function testPaymentListingSupportsFilteringSortingAndPagination(): void
    {
        $this->insertFixture('low', 10);
        $highId = $this->insertFixture('high', 30);

        $payments = Payments::getInstance()->getPayments([
            'where' => [
                'payment_type' => StandardPayment::class
            ],
            'order' => 'priority DESC',
            'limit' => 1
        ]);

        self::assertCount(1, $payments);
        self::assertSame($highId, (int)$payments[0]->getId());
    }

    private function insertFixture(string $suffix, int $priority): int
    {
        $icon = self::PREFIX . $suffix;

        $this->connection()->insert($this->table(), [
            'active' => 0,
            'payment_type' => StandardPayment::class,
            'icon' => $icon,
            'priority' => $priority
        ]);

        $QueryBuilder = $this->connection()->createQueryBuilder();

        return (int)$QueryBuilder
            ->select('id')
            ->from($this->table())
            ->where($QueryBuilder->expr()->eq('icon', ':icon'))
            ->setParameter('icon', $icon)
            ->executeQuery()
            ->fetchOne();
    }

    private function countFixture(string $suffix): int
    {
        $QueryBuilder = $this->connection()->createQueryBuilder();

        return (int)$QueryBuilder
            ->select('COUNT(*)')
            ->from($this->table())
            ->where($QueryBuilder->expr()->eq('icon', ':icon'))
            ->setParameter('icon', self::PREFIX . $suffix)
            ->executeQuery()
            ->fetchOne();
    }

    private function cleanupFixtures(): void
    {
        $QueryBuilder = $this->connection()->createQueryBuilder();
        $QueryBuilder
            ->delete($this->table())
            ->where($QueryBuilder->expr()->like('icon', ':prefix'))
            ->setParameter('prefix', self::PREFIX . '%')
            ->executeStatement();
    }

    private function connection(): Connection
    {
        return QUI::getDataBaseConnection();
    }

    private function table(): string
    {
        return QUI::getDBTableName('payments');
    }
}
