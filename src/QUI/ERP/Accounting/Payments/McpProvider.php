<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Payments;

use DateTimeImmutable;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use QUI;
use QUI\AI\MCP\ProviderInterface;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\ERP\Accounting\Payments\Api\AbstractPayment;
use QUI\ERP\Accounting\Payments\Types\Factory;
use QUI\ERP\Accounting\Payments\Types\Payment;
use QUI\Permissions\Permission;
use Throwable;

/**
 * Optional MCP administration of global payment methods.
 */
class McpProvider implements ProviderInterface
{
    private const PERMISSION = 'quiqqer.payments.mcp';
    private const ORDERS = ['priority ASC', 'priority DESC', 'id ASC', 'id DESC'];
    private const TEXT_FIELDS = ['title', 'workingTitle', 'description', 'orderInformation', 'paymentFeeTitle'];

    public function register(Builder $serverBuilder): void
    {
        try {
            Permission::checkPermission(self::PERMISSION, Server::getRequestUser());
        } catch (Throwable) {
            return;
        }

        $paymentId = ['type' => 'integer', 'minimum' => 1];
        $data = $this->getDataSchema();

        $serverBuilder->addTool(
            fn(mixed $active = null, mixed $limit = 20, mixed $offset = 0, mixed $order = 'priority ASC') =>
                $this->run(null, function () use ($active, $limit, $offset, $order): array {
                    if (
                        ($active !== null && !is_bool($active))
                        || !is_int($limit)
                        || $limit < 1
                        || $limit > 100
                        || !is_int($offset)
                        || $offset < 0
                        || !is_string($order)
                        || !in_array($order, self::ORDERS, true)
                    ) {
                        throw new QUI\Exception('Invalid payment list parameters.');
                    }

                    $Factory = $this->getFactory();
                    $where = $active === null ? [] : ['active' => (int)$active];
                    $payments = $Factory->getChildren([
                        'where' => $where,
                        'order' => $order,
                        'limit' => $offset . ',' . $limit
                    ]);
                    $result = [];

                    foreach ($payments as $Payment) {
                        if (!$Payment instanceof Payment) {
                            throw new QUI\Exception('Invalid configured payment method.');
                        }

                        $result[] = $this->parsePayment($Payment);
                    }

                    return [
                        'payments' => $result,
                        'total' => $Factory->countChildren(['where' => $where]),
                        'limit' => $limit,
                        'offset' => $offset
                    ];
                }),
            name: 'payments_list',
            description: 'Lists configured global payment methods, including disabled methods. '
                . 'Filter by active status and paginate in priority or id order. Does not evaluate checkout availability.',
            inputSchema: $this->objectSchema([
                'active' => ['type' => ['boolean', 'null'], 'default' => null],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                'order' => ['type' => 'string', 'enum' => self::ORDERS, 'default' => 'priority ASC']
            ])
        );

        $serverBuilder->addTool(
            fn(mixed $paymentId) => $this->run(null, fn(): array => $this->parsePayment($this->getPayment($paymentId))),
            name: 'payments_get',
            description: 'Reads a configured payment method, with language-keyed texts, fee and availability rules.',
            inputSchema: $this->objectSchema(['paymentId' => $paymentId], ['paymentId'])
        );

        $serverBuilder->addTool(
            fn() => $this->run(null, fn(): array => [
                'types' => array_values(array_map(
                    fn(AbstractPayment $Type): array => $this->parseType($Type),
                    $this->getPayments()->getPaymentTypes()
                ))
            ]),
            name: 'payments_types',
            description: 'Lists registered payment types and their gateway, refund and recurring-payment capabilities. '
                . 'Use the returned name with payments_create.',
            inputSchema: $this->objectSchema([])
        );

        $serverBuilder->addTool(
            fn(mixed $paymentType, mixed $data = []) => $this->run('quiqqer.payments.create', function () use (
                $paymentType,
                $data
            ): array {
                if (!is_string($paymentType) || preg_match('/^[a-f0-9]{32}$/D', $paymentType) !== 1) {
                    throw new QUI\Exception('Use a payment type name returned by payments_types.');
                }

                $data = $this->validateData($data);
                $Type = $this->getPayments()->getPaymentType($paymentType);

                return QUI::getDataBaseConnection()->transactional(function () use ($Type, $data): array {
                    $attributes = $data;
                    unset($attributes['paymentFeeTitle']);
                    $attributes['payment_type'] = get_class($Type);
                    $attributes['active'] = 0;
                    $Payment = $this->getFactory()->createChild($attributes);

                    if (isset($data['paymentFeeTitle'])) {
                        $Payment->setPaymentFeeTitle($data['paymentFeeTitle']);
                    }

                    return $this->parsePayment($Payment);
                });
            }),
            name: 'payments_create',
            description: 'Creates a disabled payment method from a name returned by payments_types. '
                . 'Optional texts are objects keyed by installed language codes. Activate separately after review.',
            inputSchema: $this->objectSchema([
                'paymentType' => ['type' => 'string', 'pattern' => '^[a-f0-9]{32}$', 'minLength' => 32, 'maxLength' => 32],
                'data' => $data
            ], ['paymentType'])
        );

        $serverBuilder->addTool(
            fn(mixed $paymentId, mixed $data) => $this->run('quiqqer.payments.edit', function () use (
                $paymentId,
                $data
            ): array {
                $Payment = $this->getPayment($paymentId);
                $data = $this->validateData($data, $Payment);

                return QUI::getDataBaseConnection()->transactional(function () use ($Payment, $data): array {
                    $attributes = $data;

                    foreach (self::TEXT_FIELDS as $field) {
                        if (!isset($data[$field])) {
                            continue;
                        }

                        $setter = 'set' . ucfirst($field);
                        $Payment->$setter($data[$field]);
                        unset($attributes[$field]);
                    }

                    if (array_key_exists('paymentFee', $data)) {
                        if ($data['paymentFee'] === null) {
                            $Payment->clearPaymentFee();
                        } else {
                            $Payment->setPaymentFee((float)$data['paymentFee']);
                        }

                        unset($attributes['paymentFee']);
                    }

                    if (isset($data['icon'])) {
                        $Payment->setIcon($data['icon']);
                        unset($attributes['icon']);
                    }

                    $Payment->setAttributes($attributes);
                    $Payment->update();
                    $Payment->refresh();

                    return $this->parsePayment($Payment);
                });
            }),
            name: 'payments_update',
            description: 'Updates only supplied fields of a global payment method. Texts are keyed by language code. '
                . 'Omitted fields and translations remain unchanged. Set paymentFee to null to remove the fee. '
                . 'Empty restriction strings clear restrictions. Type and active state cannot be changed here.',
            inputSchema: $this->objectSchema(['paymentId' => $paymentId, 'data' => $data], ['paymentId', 'data'])
        );

        foreach (['activate', 'deactivate'] as $action) {
            $serverBuilder->addTool(
                fn(mixed $paymentId) => $this->run('quiqqer.payments.edit', function () use ($paymentId, $action): array {
                    $Payment = $this->getPayment($paymentId);

                    if ($action === 'activate') {
                        $Payment->getPaymentType();
                    }

                    $Payment->$action();

                    return $this->parsePayment($Payment);
                }),
                name: 'payments_' . $action,
                description: ucfirst($action) . 's a global payment method. This changes its checkout availability.',
                inputSchema: $this->objectSchema(['paymentId' => $paymentId], ['paymentId'])
            );
        }
    }

    /**
     * Domain APIs also check their implicit permission actor. Restore it after every callback.
     *
     * @param callable(): array<string, mixed> $Callback
     * @return array<string, mixed>|CallToolResult
     */
    private function run(?string $permission, callable $Callback): array | CallToolResult
    {
        try {
            $User = Server::getRequestUser();
            Permission::checkPermission(self::PERMISSION, $User);

            if ($permission !== null) {
                Permission::checkPermission($permission, $User);
            }

            return Permission::withUser($User, $Callback);
        } catch (Throwable $Exception) {
            return ToolHelper::parseExceptionToResult($Exception);
        }
    }

    protected function getFactory(): Factory
    {
        return Factory::getInstance();
    }

    protected function getPayments(): Payments
    {
        return Payments::getInstance();
    }

    private function getPayment(mixed $paymentId): Payment
    {
        if (!is_int($paymentId) || $paymentId < 1) {
            throw new QUI\Exception('Invalid payment id.');
        }

        return $this->getFactory()->getChild($paymentId);
    }

    /** @return array<string, mixed> */
    private function parseType(AbstractPayment $Type): array
    {
        return [
            'name' => $Type->getName(),
            'title' => $Type->getTitle(),
            'description' => $Type->getDescription(),
            'typeCode' => $Type->getTypeCode()->value,
            'gateway' => $Type->isGateway(),
            'unique' => $Type->isUnique(),
            'refundSupport' => $Type->refundSupport(),
            'recurringPayments' => $Type->supportsRecurringPayments(),
            'recurringPaymentsOnly' => $Type->supportsRecurringPaymentsOnly()
        ];
    }

    /** @return array<string, mixed> */
    private function parsePayment(Payment $Payment): array
    {
        $values = $Payment->toArray();
        $fields = array_merge(array_keys($this->getDataSchema()['properties']), ['id', 'active']);
        $result = array_intersect_key($values, array_flip($fields));
        $result['id'] = $Payment->getId();
        $result['active'] = $Payment->isActive();
        $result['paymentType'] = null;

        try {
            $result['paymentType'] = $this->parseType($Payment->getPaymentType());
        } catch (QUI\Exception) {
            // A missing provider must not prevent inspection or deactivation of its configured method.
        }

        $result['paymentFeeTitle'] = [];

        foreach (QUI::availableLanguages() as $language) {
            $result['paymentFeeTitle'][$language] = QUI::getLocale()->getByLang(
                $language,
                'quiqqer/payments',
                'payment.' . $Payment->getId() . '.paymentFeeTitle'
            );
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function getDataSchema(): array
    {
        $properties = [];
        $translations = [];

        foreach (QUI::availableLanguages() as $language) {
            $translations[$language] = ['type' => 'string', 'maxLength' => 20000];
        }

        foreach (self::TEXT_FIELDS as $field) {
            $properties[$field] = $this->objectSchema($translations);
            $properties[$field]['minProperties'] = 1;
            $properties[$field]['description'] =
                'Translations keyed by installed language code, for example {"en":"Invoice"}.';
        }

        $properties['paymentFee'] = [
            'type' => ['number', 'null'],
            'minimum' => 0,
            'description' => 'Payment fee amount. Null explicitly removes the fee.'
        ];
        $properties['priority'] = ['type' => 'integer', 'minimum' => 0];
        $properties['icon'] = ['type' => 'string', 'maxLength' => 255, 'description' => 'Existing QUIQQER media URL.'];

        foreach (['date_from', 'date_until'] as $field) {
            $properties[$field] = [
                'type' => 'string',
                'maxLength' => 19,
                'pattern' => '^(\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}(:\\d{2})?)?$',
                'description' => 'Local server date/time as YYYY-MM-DDTHH:MM or YYYY-MM-DDTHH:MM:SS. Empty clears it.'
            ];
        }

        foreach (['purchase_quantity_from', 'purchase_quantity_until'] as $field) {
            $properties[$field] = ['type' => 'integer', 'minimum' => 0, 'description' => 'Zero means no bound.'];
        }

        foreach (['purchase_value_from', 'purchase_value_until'] as $field) {
            $properties[$field] = ['type' => ['number', 'null'], 'minimum' => 0, 'description' => 'Null clears the bound.'];
        }

        foreach (['areas', 'articles', 'categories', 'user_groups', 'currencies'] as $field) {
            $properties[$field] = [
                'type' => 'string',
                'maxLength' => 8192,
                'description' => 'Comma-separated identifiers; empty clears the restriction. '
                    . 'user_groups uses u<user id/UUID> and g<group id/UUID>; currencies uses codes such as EUR,USD.',
                'pattern' => match ($field) {
                    'currencies' => '^([A-Z]{3}(,[A-Z]{3})*)?$',
                    'user_groups' => '^([ug][a-zA-Z0-9-]+(,[ug][a-zA-Z0-9-]+)*)?$',
                    default => '^([a-zA-Z0-9-]+(,[a-zA-Z0-9-]+)*)?$'
                }
            ];
        }

        return $this->objectSchema($properties);
    }

    /** @return array<string, mixed> */
    private function validateData(mixed $data, ?Payment $Payment = null): array
    {
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new QUI\Exception('Payment data must be an object containing named fields.');
        }

        $schema = $this->getDataSchema();

        foreach ($data as $field => $value) {
            if (!isset($schema['properties'][$field])) {
                throw new QUI\Exception('Unsupported payment data field: ' . $field);
            }

            $definition = $schema['properties'][$field];
            $type = $definition['type'];

            if (is_array($type) && $value === null) {
                continue;
            }

            $valid = match (is_array($type) ? $type[0] : $type) {
                'string' => is_string($value)
                    && mb_strlen($value) <= $definition['maxLength']
                    && (!isset($definition['pattern']) || preg_match('/' . $definition['pattern'] . '/D', $value) === 1),
                'integer' => is_int($value) && $value >= 0,
                'number' => (is_int($value) || is_float($value)) && is_finite((float)$value) && $value >= 0,
                'object' => is_array($value) && $value !== [],
                default => false
            };

            if (!$valid) {
                throw new QUI\Exception('Invalid payment data field: ' . $field);
            }

            if ($type === 'object') {
                foreach ($value as $language => $text) {
                    if (
                        !in_array($language, QUI::availableLanguages(), true)
                        || !is_string($text)
                        || mb_strlen($text) > 20000
                    ) {
                        throw new QUI\Exception('Invalid payment translation. Use installed language codes and strings.');
                    }
                }
            }

            if ($field === 'icon') {
                $url = parse_url($value);

                if (
                    $url === false
                    || !in_array(strtolower($url['scheme'] ?? ''), ['', 'http', 'https'], true)
                    || isset($url['user'])
                    || isset($url['pass'])
                    || preg_match('/[\x00-\x20\x7f]/', $value) === 1
                    || !QUI\Projects\Media\Utils::isMediaUrl($value)
                ) {
                    throw new QUI\Exception('Payment icon must be a QUIQQER media URL.');
                }
            }
        }

        $attributes = $Payment === null ? $data : array_replace($Payment->getAttributes(), $data);
        $dates = [];

        foreach (['date_from', 'date_until'] as $field) {
            $value = $attributes[$field] ?? '';

            if (!$value) {
                continue;
            }

            // Stored dates may use a space; new input always uses the documented ISO separator.
            $value = str_replace(' ', 'T', $value);
            $format = strlen($value) === 16 ? 'Y-m-d\TH:i' : 'Y-m-d\TH:i:s';
            $Date = DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($Date === false || $Date->format($format) !== $value) {
                throw new QUI\Exception('Invalid payment date: ' . $field);
            }

            $dates[$field] = $Date;
        }

        if (isset($dates['date_from'], $dates['date_until']) && $dates['date_from'] > $dates['date_until']) {
            throw new QUI\Exception('Payment start date must not exceed the end date.');
        }

        foreach (['purchase_quantity', 'purchase_value'] as $prefix) {
            $minimum = $attributes[$prefix . '_from'] ?? 0;
            $maximum = $attributes[$prefix . '_until'] ?? 0;

            if ($maximum > 0 && $minimum > $maximum) {
                throw new QUI\Exception('Payment minimum must not exceed its maximum: ' . $prefix);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    private function objectSchema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties === [] ? new \stdClass() : $properties,
            'required' => $required
        ];
    }
}
