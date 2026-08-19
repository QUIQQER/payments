<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use QUI;
use QUI\Ajax;
use QUI\ERP\Accounting\Payments\Methods\Standard\Payment as StandardPayment;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use ReflectionProperty;

class AjaxEndpointsTest extends SqlitePaymentTestCase
{
    /** @var array<string, array{callable: callable, params: array<array-key, mixed>}> */
    private array $originalCallables;

    /** @var array<string, mixed> */
    private array $originalPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCallables = Ajax::getRegisteredCallables();
        $this->originalPermissions = $this->ajaxProperty('permissions')->getValue();
        QUI::getMessagesHandler()->clear();
    }

    protected function tearDown(): void
    {
        $this->ajaxProperty('callables')->setValue(null, $this->originalCallables);
        $this->ajaxProperty('permissions')->setValue(null, $this->originalPermissions);
        QUI::getMessagesHandler()->clear();

        parent::tearDown();
    }

    public function testAllEndpointsRegisterTheirPublicNamesParametersAndPermissions(): void
    {
        foreach ($this->endpointFiles() as $file) {
            include $file;
        }

        $callables = Ajax::getRegisteredCallables();
        $permissions = $this->ajaxProperty('permissions')->getValue();
        $expected = [
            'package_quiqqer_payments_ajax_backend_activate' => [['paymentId'], true],
            'package_quiqqer_payments_ajax_backend_create' => [['paymentType'], true],
            'package_quiqqer_payments_ajax_backend_deactivate' => [['paymentId'], true],
            'package_quiqqer_payments_ajax_backend_delete' => [['paymentId'], true],
            'package_quiqqer_payments_ajax_backend_getPayment' => [['paymentId'], true],
            'package_quiqqer_payments_ajax_backend_getPaymentTypes' => [[], true],
            'package_quiqqer_payments_ajax_backend_getPayments' => [[], true],
            'package_quiqqer_payments_ajax_backend_update' => [['paymentId', 'data'], true],
            'package_quiqqer_payments_ajax_frontend_getAvailablePaymentCurrencies' => [['paymentId'], false],
            'package_quiqqer_log_ajax_logPaymentsError' => [['errMsg', 'errCode'], false]
        ];

        foreach ($expected as $name => [$params, $requiresAdmin]) {
            self::assertArrayHasKey($name, $callables);
            self::assertSame($params, $callables[$name]['params']);

            if ($requiresAdmin) {
                self::assertSame('Permission::checkAdminUser', $permissions[$name]);
            } else {
                self::assertArrayNotHasKey($name, $permissions);
            }
        }
    }

    public function testActivateAndDeactivateEndpointsPersistLifecycleState(): void
    {
        $id = $this->insertPayment(['active' => 0]);
        $this->includeEndpoint('backend/activate.php');
        $activated = $this->callEndpoint(
            'package_quiqqer_payments_ajax_backend_activate',
            $id
        );

        self::assertSame(1, $activated['active']);
        self::assertSame(1, (int)$this->storedValue($id, 'active'));

        $this->includeEndpoint('backend/deactivate.php');
        $deactivated = $this->callEndpoint(
            'package_quiqqer_payments_ajax_backend_deactivate',
            $id
        );

        self::assertSame(0, $deactivated['active']);
        self::assertSame(0, (int)$this->storedValue($id, 'active'));
    }

    public function testGetPaymentAndListEndpointsSerializePersistedPayments(): void
    {
        $firstId = $this->insertPayment(['active' => 1, 'icon' => 'raw-icon', 'priority' => 10]);
        $secondId = $this->insertPayment(['active' => 0, 'priority' => 20]);
        $this->includeEndpoint('backend/getPayment.php');
        $payment = $this->callEndpoint(
            'package_quiqqer_payments_ajax_backend_getPayment',
            $firstId
        );

        self::assertSame($firstId, $payment['id']);
        self::assertSame('raw-icon', $payment['icon']);

        $this->includeEndpoint('backend/getPayments.php');
        $payments = $this->callEndpoint('package_quiqqer_payments_ajax_backend_getPayments');

        self::assertSame([$firstId, $secondId], array_column($payments, 'id'));
    }

    public function testUpdateEndpointPersistsFeeAndOrdinaryAttributes(): void
    {
        $id = $this->insertPayment(['active' => 1]);
        $this->includeEndpoint('backend/update.php');
        $result = $this->callEndpoint(
            'package_quiqqer_payments_ajax_backend_update',
            $id,
            json_encode([
                'paymentFee' => '7.25',
                'priority' => 44,
                'active' => 1
            ], JSON_THROW_ON_ERROR)
        );

        self::assertSame(7.25, (float)$result['paymentFee']);
        self::assertSame(44, $result['priority']);
        self::assertSame(7.25, (float)$this->storedValue($id, 'paymentFee'));
        self::assertSame(44, (int)$this->storedValue($id, 'priority'));
    }

    public function testDeleteEndpointRemovesOnlySelectedPayment(): void
    {
        $deletedId = $this->insertPayment(['icon' => 'delete-me']);
        $retainedId = $this->insertPayment(['icon' => 'keep-me']);
        $this->includeEndpoint('backend/delete.php');

        $this->callEndpoint('package_quiqqer_payments_ajax_backend_delete', $deletedId);

        self::assertFalse($this->connection->fetchOne(
            'SELECT id FROM ' . $this->paymentTable() . ' WHERE id = ?',
            [$deletedId]
        ));
        self::assertSame($retainedId, (int)$this->connection->fetchOne(
            'SELECT id FROM ' . $this->paymentTable() . ' WHERE id = ?',
            [$retainedId]
        ));
    }

    public function testPaymentTypesEndpointReturnsBuiltInProviderMetadata(): void
    {
        $this->includeEndpoint('backend/getPaymentTypes.php');

        $types = $this->callEndpoint('package_quiqqer_payments_ajax_backend_getPaymentTypes');
        $names = array_column($types, 'name');

        self::assertContains(md5(StandardPayment::class), $names);
    }

    /**
     * @return list<string>
     */
    private function endpointFiles(): array
    {
        return array_merge(
            glob(dirname(__DIR__, 2) . '/ajax/backend/*.php') ?: [],
            glob(dirname(__DIR__, 2) . '/ajax/frontend/*.php') ?: []
        );
    }

    private function includeEndpoint(string $relativePath): void
    {
        include dirname(__DIR__, 2) . '/ajax/' . $relativePath;
    }

    private function callEndpoint(string $name, mixed ...$arguments): mixed
    {
        $callable = Ajax::getRegisteredCallables()[$name]['callable'];

        return $callable(...$arguments);
    }

    private function storedValue(int $id, string $column): mixed
    {
        $allowedColumns = ['active', 'paymentFee', 'priority'];
        self::assertContains($column, $allowedColumns);

        return $this->connection->fetchOne(
            'SELECT ' . $column . ' FROM ' . $this->paymentTable() . ' WHERE id = ?',
            [$id]
        );
    }

    private function ajaxProperty(string $name): ReflectionProperty
    {
        return new ReflectionProperty(Ajax::class, $name);
    }
}
