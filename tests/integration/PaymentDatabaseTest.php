<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI\ERP\Accounting\Payments\Methods\Standard\Payment as StandardPayment;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Accounting\Payments\Types\Factory;

class PaymentDatabaseTest extends SqlitePaymentTestCase
{
    private const PREFIX = 'phpunit_payments_';

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

        $this->connection->insert($this->paymentTable(), [
            'active' => 0,
            'payment_type' => StandardPayment::class,
            'icon' => $icon,
            'priority' => $priority
        ]);

        $QueryBuilder = $this->connection->createQueryBuilder();

        return (int)$QueryBuilder
            ->select('id')
            ->from($this->paymentTable())
            ->where($QueryBuilder->expr()->eq('icon', ':icon'))
            ->setParameter('icon', $icon)
            ->executeQuery()
            ->fetchOne();
    }

    private function countFixture(string $suffix): int
    {
        $QueryBuilder = $this->connection->createQueryBuilder();

        return (int)$QueryBuilder
            ->select('COUNT(*)')
            ->from($this->paymentTable())
            ->where($QueryBuilder->expr()->eq('icon', ':icon'))
            ->setParameter('icon', self::PREFIX . $suffix)
            ->executeQuery()
            ->fetchOne();
    }

    private function cleanupFixtures(): void
    {
        $QueryBuilder = $this->connection->createQueryBuilder();
        $QueryBuilder
            ->delete($this->paymentTable())
            ->where($QueryBuilder->expr()->like('icon', ':prefix'))
            ->setParameter('prefix', self::PREFIX . '%')
            ->executeStatement();
    }
}
