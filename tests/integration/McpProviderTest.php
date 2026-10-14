<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments\Tests\Integration;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Accounting\Payments\Payments;
use QUI\ERP\Accounting\Payments\Settings;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\McpPaymentFactory;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\SqlitePaymentTestCase;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\TestableMcpProvider;
use QUI\ERP\Accounting\Payments\Tests\Fixtures\TestPaymentMethod;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Manager;
use QUI\Permissions\Permission;
use ReflectionProperty;

class McpProviderTest extends SqlitePaymentTestCase
{
    private mixed $previousRequestUser;
    private ?Manager $previousRights;
    private mixed $previousSettings;
    private McpPaymentFactory $Factory;
    private TestableMcpProvider $Provider;
    /** @var array<string, callable> */
    private array $tools;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousRequestUser = (new ReflectionProperty(Server::class, 'RequestUser'))->getValue();
        $this->previousRights = QUI::$Rights;
        $Settings = Settings::getInstance();
        $Config = new ReflectionProperty(Settings::class, 'Config');
        $this->previousSettings = $Config->getValue($Settings);
        $Config->setValue($Settings, $this->createMock(QUI\Config::class));
        $this->Factory = new McpPaymentFactory();
        $Payments = $this->createMock(Payments::class);
        $Type = new TestPaymentMethod();
        $Payments->method('getPaymentTypes')->willReturn([$Type->getName() => $Type]);
        $Payments->method('getPaymentType')->willReturnCallback(function (string $name) use ($Type) {
            if ($name !== $Type->getName()) {
                throw new QUI\Exception('Unknown payment type.');
            }

            return $Type;
        });
        $this->Provider = new TestableMcpProvider($this->Factory, $Payments);
        $this->setRequestUser(QUI::getUsers()->getSystemUser());
        $this->tools = $this->getTools();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $this->previousRequestUser);
        (new ReflectionProperty(Settings::class, 'Config'))->setValue(Settings::getInstance(), $this->previousSettings);
        QUI::$Rights = $this->previousRights;

        parent::tearDown();
    }

    public function testRegistrationAndRevokedMcpPermissionAtEveryCallback(): void
    {
        self::assertSame([
            'payments_list',
            'payments_get',
            'payments_types',
            'payments_create',
            'payments_update',
            'payments_activate',
            'payments_deactivate'
        ], array_keys($this->tools));
        $this->restrictUser([]);
        self::assertSame([], $this->getTools());

        $arguments = [
            'payments_list' => [],
            'payments_get' => [1],
            'payments_types' => [],
            'payments_create' => [(new TestPaymentMethod())->getName()],
            'payments_update' => [1, ['priority' => 2]],
            'payments_activate' => [1],
            'payments_deactivate' => [1]
        ];

        foreach ($arguments as $name => $parameters) {
            $this->assertError(($this->tools[$name])(...$parameters));
        }

        self::assertSame(0, $this->Factory->countChildren());
    }

    public function testReadOnlyAccountCannotWriteDespitePrivilegedAmbientUser(): void
    {
        $id = $this->insertPayment(['priority' => 10]);
        $this->restrictUser(['quiqqer.payments.mcp' => true]);
        self::assertIsArray(($this->tools['payments_get'])($id));
        self::assertIsArray(($this->tools['payments_types'])());

        foreach (['payments_activate', 'payments_deactivate'] as $name) {
            $this->assertError(($this->tools[$name])($id));
        }

        $this->assertError(($this->tools['payments_update'])($id, ['priority' => 20]));
        $this->assertError(($this->tools['payments_create'])((new TestPaymentMethod())->getName()));
        self::assertSame(10, (int)$this->loadPayment($id)->getAttribute('priority'));
        self::assertFalse($this->loadPayment($id)->isActive());
    }

    public function testRestrictedEditorPersistsPartialUpdateAndRestoresPermissionActor(): void
    {
        $id = $this->insertPayment(['paymentFee' => 4.5, 'priority' => 10, 'currencies' => 'EUR']);
        $this->restrictUser(['quiqqer.payments.mcp' => true, 'quiqqer.payments.edit' => true]);
        $PreviousUser = (new ReflectionProperty(Permission::class, 'User'))->getValue();

        $result = ($this->tools['payments_update'])($id, [
            'priority' => 20,
            'currencies' => 'EUR,USD',
            'title' => ['en' => 'Updated title']
        ]);

        self::assertIsArray($result);
        $Stored = $this->loadPayment($id);
        self::assertSame(20, (int)$Stored->getAttribute('priority'));
        self::assertSame('EUR,USD', $Stored->getAttribute('currencies'));
        self::assertEqualsWithDelta(4.5, $Stored->getPaymentFee(), 0.00001);
        self::assertSame(
            ['en' => 'Updated title'],
            $this->Factory->loadedPayments[0]->localeChanges['payment.' . $id . '.title']
        );
        self::assertSame($PreviousUser, (new ReflectionProperty(Permission::class, 'User'))->getValue());

        $result = ($this->tools['payments_update'])($id, ['paymentFee' => null, 'currencies' => '']);
        self::assertIsArray($result);
        self::assertFalse($this->loadPayment($id)->hasPaymentFee());
        self::assertSame('', $this->loadPayment($id)->getAttribute('currencies'));
    }

    public function testRestrictedEditorActivatesAndDeactivatesStoredPayment(): void
    {
        $id = $this->insertPayment();
        $this->restrictUser(['quiqqer.payments.mcp' => true, 'quiqqer.payments.edit' => true]);
        $activated = ($this->tools['payments_activate'])($id);
        self::assertIsArray($activated);
        self::assertTrue($activated['active']);
        self::assertTrue($this->loadPayment($id)->isActive());
        $deactivated = ($this->tools['payments_deactivate'])($id);
        self::assertIsArray($deactivated);
        self::assertFalse($deactivated['active']);
        self::assertFalse($this->loadPayment($id)->isActive());
    }

    public function testCreationUsesRegisteredTypeAndRequiresOnlyCreatePermission(): void
    {
        $this->restrictUser(['quiqqer.payments.mcp' => true, 'quiqqer.payments.create' => true]);
        $result = ($this->tools['payments_create'])((new TestPaymentMethod())->getName(), [
            'priority' => 12,
            'paymentFee' => 3.5,
            'title' => ['en' => 'MCP fixture'],
            'purchase_value_from' => 10.0,
            'currencies' => 'EUR'
        ]);

        self::assertIsArray($result);
        self::assertFalse($result['active']);
        self::assertSame(1, $this->Factory->countChildren());
        $Stored = $this->loadPayment($result['id']);
        self::assertSame(TestPaymentMethod::class, $Stored->getAttribute('payment_type'));
        self::assertSame(12, (int)$Stored->getAttribute('priority'));
        self::assertEqualsWithDelta(3.5, $Stored->getPaymentFee(), 0.00001);
        self::assertSame(['en' => 'MCP fixture'], $this->Factory->createdLocales['payment.' . $result['id'] . '.title']);

        $this->assertError(($this->tools['payments_create'])(TestPaymentMethod::class));
        self::assertSame(1, $this->Factory->countChildren());
    }

    public function testListingFiltersSortsPaginatesAndCountsWithoutLeakingClassNames(): void
    {
        $this->insertPayment(['active' => 1, 'priority' => 10]);
        $id = $this->insertPayment(['active' => 1, 'priority' => 20]);
        $this->insertPayment(['active' => 0, 'priority' => 30]);
        $result = ($this->tools['payments_list'])(true, 1, 1);

        self::assertIsArray($result);
        self::assertSame(2, $result['total']);
        self::assertCount(1, $result['payments']);
        self::assertSame($id, $result['payments'][0]['id']);
        self::assertArrayNotHasKey('payment_type', $result['payments'][0]);
        self::assertArrayHasKey('paymentFeeTitle', $result['payments'][0]);
        $result = ($this->tools['payments_list'])(false);
        self::assertIsArray($result);
        self::assertSame(1, $result['total']);
        $result = ($this->tools['payments_list'])(null, 1, 3);
        self::assertIsArray($result);
        self::assertSame(3, $result['total']);
        self::assertSame([], $result['payments']);

        $types = ($this->tools['payments_types'])();
        self::assertIsArray($types);
        self::assertSame((new TestPaymentMethod())->getName(), $types['types'][0]['name']);
        self::assertFalse($types['types'][0]['gateway']);
        self::assertFalse($types['types'][0]['refundSupport']);
        self::assertFalse($types['types'][0]['recurringPayments']);
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('invalidData')]
    public function testInvalidDataCannotPartiallyWriteOrCreate(array $data): void
    {
        $id = $this->insertPayment(['priority' => 10, 'paymentFee' => 4.5]);
        $this->assertError(($this->tools['payments_update'])($id, ['title' => ['en' => 'Must not be saved']] + $data));
        $this->assertError(($this->tools['payments_create'])((new TestPaymentMethod())->getName(), $data));
        self::assertSame(1, $this->Factory->countChildren());
        self::assertSame(10, (int)$this->loadPayment($id)->getAttribute('priority'));
        self::assertEqualsWithDelta(4.5, $this->loadPayment($id)->getPaymentFee(), 0.00001);

        foreach ($this->Factory->loadedPayments as $Payment) {
            self::assertSame([], $Payment->localeChanges);
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidData(): iterable
    {
        yield 'arbitrary class' => [['payment_type' => TestPaymentMethod::class]];
        yield 'activation bypass' => [['active' => 1]];
        yield 'immutable id' => [['id' => 123]];
        yield 'negative priority' => [['priority' => -1]];
        yield 'numeric string' => [['priority' => '12']];
        yield 'negative fee' => [['paymentFee' => -3]];
        yield 'infinite fee' => [['paymentFee' => INF]];
        yield 'unknown language' => [['description' => ['unknown-language' => 'text']]];
        yield 'invalid translation' => [['description' => ['en' => []]]];
        yield 'translation list' => [['description' => ['text']]];
        yield 'invalid date' => [['date_from' => '2026-02-30T12:00']];
        yield 'inverted dates' => [['date_from' => '2026-12-01T12:00', 'date_until' => '2026-01-01T12:00']];
        yield 'inverted quantity' => [['purchase_quantity_from' => 10, 'purchase_quantity_until' => 5]];
        yield 'inverted value' => [['purchase_value_from' => 10, 'purchase_value_until' => 5]];
        yield 'arbitrary icon' => [['icon' => 'javascript:alert(1)']];
        yield 'script masquerading as media' => [['icon' => 'javascript:image.php?project=default&id=1']];
        yield 'icon credentials' => [['icon' => 'https://secret:password@example.com/image.php?project=default&id=1']];
        yield 'invalid currency' => [['currencies' => 'EUR,not-a-currency']];
        yield 'invalid users' => [['user_groups' => '123']];
    }

    public function testMissingAndSpecialPaymentIdsAndUnsafeSortReturnErrors(): void
    {
        foreach ([0, -1, 999999, '1', 1.5, true, null, []] as $id) {
            $this->assertError(($this->tools['payments_get'])($id));
            $this->assertError(($this->tools['payments_activate'])($id));
            $this->assertError(($this->tools['payments_deactivate'])($id));
            $this->assertError(($this->tools['payments_update'])($id, ['priority' => 1]));
        }

        $this->assertError(($this->tools['payments_list'])(null, 0));
        $this->assertError(($this->tools['payments_list'])(null, 101));
        $this->assertError(($this->tools['payments_list'])(null, 20, -1));
        $this->assertError(($this->tools['payments_list'])(null, 20, 0, 'priority; DROP TABLE payments'));
        $this->assertError(($this->tools['payments_list'])('false'));
        $this->assertError(($this->tools['payments_list'])(null, '20'));
        $this->assertError(($this->tools['payments_list'])(null, 20, '0'));
        $this->assertError(($this->tools['payments_list'])(null, 20, 0, []));
    }

    public function testIncorrectlyTransferredPayloadsReturnErrorsWithoutChangingPayments(): void
    {
        $id = $this->insertPayment(['priority' => 10]);
        $PreviousUser = (new ReflectionProperty(Permission::class, 'User'))->getValue();

        foreach ([null, true, 'data', 123, [['priority' => 20]]] as $data) {
            $this->assertError(($this->tools['payments_update'])($id, $data));
            $this->assertError(($this->tools['payments_create'])((new TestPaymentMethod())->getName(), $data));
        }

        foreach ([null, true, 123, [], 'invalid-type', str_repeat('a', 33)] as $type) {
            $this->assertError(($this->tools['payments_create'])($type));
        }

        self::assertSame(1, $this->Factory->countChildren());
        self::assertSame(10, (int)$this->loadPayment($id)->getAttribute('priority'));
        self::assertSame($PreviousUser, (new ReflectionProperty(Permission::class, 'User'))->getValue());
    }

    public function testSdkRegistersToolsAndValidatesTheirWireSchemas(): void
    {
        if (!class_exists(\Mcp\Capability\Discovery\SchemaValidator::class)) {
            self::markTestSkipped('Optional MCP SDK is not installed; callback validation is tested separately.');
        }

        $Builder = new Builder();
        $this->Provider->register($Builder);
        $definitions = (new ReflectionProperty(Builder::class, 'tools'))->getValue($Builder);
        $Registry = new \Mcp\Capability\Registry();
        (new \Mcp\Capability\Registry\Loader\ReflectedElementLoader($definitions))->load($Registry);
        self::assertCount(7, $Registry->getTools()->references);
        $Validator = new \Mcp\Capability\Discovery\SchemaValidator();
        $samples = [
            'payments_list' => ['active' => false, 'limit' => 20, 'offset' => 0, 'order' => 'priority ASC'],
            'payments_get' => ['paymentId' => 1],
            'payments_types' => [],
            'payments_create' => [
                'paymentType' => (new TestPaymentMethod())->getName(),
                'data' => ['title' => ['en' => 'Invoice'], 'paymentFee' => 2.5]
            ],
            'payments_update' => ['paymentId' => 1, 'data' => ['paymentFee' => null]],
            'payments_activate' => ['paymentId' => 1],
            'payments_deactivate' => ['paymentId' => 1]
        ];

        foreach ($definitions as $definition) {
            $name = $definition['name'];
            $schema = $definition['inputSchema'];
            self::assertSame([], $Validator->validateAgainstJsonSchema($samples[$name], $schema), $name);
            self::assertNotSame([], $Validator->validateAgainstJsonSchema($samples[$name] + ['unknown' => 1], $schema));

            if (isset($samples[$name]['paymentId'])) {
                $invalid = $samples[$name];
                $invalid['paymentId'] = '1';
                self::assertNotSame([], $Validator->validateAgainstJsonSchema($invalid, $schema));
            }

            if (isset($samples[$name]['data'])) {
                $invalid = $samples[$name];
                $invalid['data'] = ['title' => ['unregistered-language' => 'Text']];
                self::assertNotSame([], $Validator->validateAgainstJsonSchema($invalid, $schema));
                $invalid['data'] = ['active' => true];
                self::assertNotSame([], $Validator->validateAgainstJsonSchema($invalid, $schema));
            }
        }
    }

    public function testMissingProviderCanBeInspectedAndDeactivatedButNotActivated(): void
    {
        $id = $this->insertPayment(['active' => 1, 'payment_type' => 'Missing\\PaymentProvider']);
        $result = ($this->tools['payments_get'])($id);
        self::assertIsArray($result);
        self::assertNull($result['paymentType']);
        $this->assertError(($this->tools['payments_activate'])($id));
        $result = ($this->tools['payments_deactivate'])($id);
        self::assertIsArray($result);
        self::assertFalse($this->loadPayment($id)->isActive());
    }

    public function testUpdateChecksRangesAgainstExistingBounds(): void
    {
        $id = $this->insertPayment(['purchase_quantity_from' => 10]);
        $this->assertError(($this->tools['payments_update'])($id, ['purchase_quantity_until' => 5]));
        self::assertSame(0, (int)$this->loadPayment($id)->getAttribute('purchase_quantity_until'));
        $result = ($this->tools['payments_update'])($id, [
            'date_from' => '2026-01-01T12:00',
            'date_until' => '2026-12-01T12:00:00',
            'user_groups' => 'u123,g456',
            'areas' => '1,2',
            'articles' => '123',
            'categories' => '1,2',
            'purchase_value_from' => 10.0,
            'purchase_value_until' => 20.0,
            'purchase_quantity_until' => 20
        ]);
        self::assertIsArray($result);
        self::assertSame('u123,g456', $this->loadPayment($id)->getAttribute('user_groups'));
        $result = ($this->tools['payments_update'])($id, ['date_from' => '', 'purchase_value_from' => null]);
        self::assertIsArray($result);
        self::assertSame('', $this->loadPayment($id)->getAttribute('date_from'));
        self::assertNull($this->loadPayment($id)->getAttribute('purchase_value_from'));
    }

    public function testFeeTranslationsAndMediaReferencesAreApplied(): void
    {
        $id = $this->insertPayment();
        $icon = '/image.php?project=default&id=123';
        $result = ($this->tools['payments_update'])($id, [
            'description' => ['en' => 'Payment description'],
            'workingTitle' => ['en' => 'Internal title'],
            'orderInformation' => ['en' => 'Payment instructions'],
            'paymentFeeTitle' => ['en' => 'Payment fee'],
            'paymentFee' => 2.5,
            'icon' => $icon
        ]);
        self::assertIsArray($result);
        self::assertEqualsWithDelta(2.5, $this->loadPayment($id)->getPaymentFee(), 0.00001);
        self::assertSame($icon, $this->loadPayment($id)->getAttribute('icon'));
        self::assertSame(4, count($this->Factory->loadedPayments[0]->localeChanges));
        self::assertSame(
            ['en' => 'Payment fee'],
            $this->Factory->loadedPayments[0]->localeChanges['payment.' . $id . '.paymentFeeTitle']
        );

        $created = ($this->tools['payments_create'])((new TestPaymentMethod())->getName(), [
            'icon' => $icon,
            'paymentFeeTitle' => ['en' => 'Payment fee']
        ]);
        self::assertIsArray($created);
        self::assertSame($icon, $this->loadPayment($created['id'])->getAttribute('icon'));
        self::assertSame(
            ['en' => 'Payment fee'],
            $this->Factory->loadedPayments[1]->localeChanges['payment.' . $created['id'] . '.paymentFeeTitle']
        );
    }

    /** @return array<string, callable> */
    private function getTools(): array
    {
        $Builder = new Builder();
        $this->Provider->register($Builder);
        $definitions = (new ReflectionProperty(Builder::class, 'tools'))->getValue($Builder);
        $tools = [];

        foreach ($definitions as $definition) {
            self::assertFalse($definition['inputSchema']['additionalProperties']);
            $tools[$definition['name']] = $definition['handler'];
        }

        return $tools;
    }

    /** @param array<string, bool> $permissions */
    private function restrictUser(array $permissions): void
    {
        $User = $this->createMock(User::class);
        $User->method('getUUID')->willReturn('mcp-payments-test-user');
        $User->method('getGroups')->willReturn([]);
        $Manager = $this->createMock(Manager::class);
        $Manager->method('getPermissions')->willReturn($permissions);
        QUI::$Rights = $Manager;
        $this->setRequestUser($User);
    }

    private function setRequestUser(User $User): void
    {
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $User);
    }

    private function assertError(mixed $result): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
    }
}
