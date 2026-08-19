<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI;
use QUI\ERP\Accounting\Payments\Gateway\Gateway;
use QUI\ERP\Accounting\Payments\Methods\Standard\Payment;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Currency\Currency;
use QUI\ERP\Order\AbstractOrder;
use QUI\Update;

class GatewayPurchaseTest extends SqlitePaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Update::importDatabase(OPT_DIR . 'quiqqer/payment-transactions/database.xml');
        Update::importDatabase(OPT_DIR . 'quiqqer/order/database.xml');
    }

    public function testPurchaseCreatesTransactionAndOrderHistoryOnSqlite(): void
    {
        $Currency = new Currency([
            'currency' => 'EUR',
            'rate' => 1,
            'autoupdate' => 0,
            'precision' => 2
        ]);
        $Payment = new Payment();
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getUUID')->willReturn('gateway-order');
        $Order->expects(self::once())
            ->method('addHistory')
            ->with(self::callback(static function (string $message): bool {
                return str_contains($message, '25') && str_contains($message, 'EUR');
            }));

        $Transaction = Gateway::getInstance()->purchase(
            25.75,
            $Currency,
            $Order,
            $Payment,
            ['providerReference' => 'ref-123']
        );
        $stored = $this->connection->fetchAssociative(
            'SELECT * FROM ' . QUI::getDBTableName('payment_transactions') . ' WHERE txid = ?',
            [$Transaction->getTxId()]
        );

        self::assertIsArray($stored);
        self::assertSame('gateway-order', $stored['hash']);
        self::assertSame('gateway-order', $stored['global_process_id']);
        self::assertEqualsWithDelta(25.75, (float)$stored['amount'], 0.00001);
        self::assertSame($Payment->getName(), $stored['payment']);
    }

    public function testInvalidScalarOrderCannotPopulateGateway(): void
    {
        $Gateway = Gateway::getInstance();
        $Gateway->setOrder('phpunit-missing-order');

        self::assertNull($Gateway->getOrder());
    }
}
