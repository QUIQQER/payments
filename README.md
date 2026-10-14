![QUIQQER Payments](bin/images/Readme.jpg)

# QUIQQER Payments

QUIQQER Payments provides the common payment-method API and administration for
QUIQQER ERP. It includes cash, invoice, advance payment, and standard payment
methods and acts as the foundation for gateway packages such as Stripe or
PayPal.

## Features

- Central API for payment methods and payment providers
- Administration of payment methods, availability, fees, icons, and priority
- Restrictions by user/group, area, currency, date, quantity, and order value
- Payment gateway request handling
- Recurring-payment contracts for subscription-capable providers
- Built-in cash, invoice, advance-payment, and standard methods

## Installation

Install the package through the QUIQQER package manager or Composer:

```bash
composer require quiqqer/payments
```

Run the QUIQQER setup from the installation root after installation or after
updating the package XML files:

```bash
./console setup
```

## Configuration and usage

Payment methods are managed in the QUIQQER administration under the ERP/shop
payment settings. Create or edit a payment method there, select its provider
type, configure its availability, and activate it for use in the order process.

Gateway packages register their payment types through the `payment` provider in
`package.xml`. Applications can use
`QUI\ERP\Accounting\Payments\Payments::getInstance()` to retrieve configured or
user-available payment methods.

## MCP administration

With the optional `quiqqer/ai-mcp` package installed, the MCP provider exposes
`payments_list`, `payments_get`, `payments_types`, `payments_create`,
`payments_update`, `payments_activate`, and `payments_deactivate`.
Run setup after updating the provider declaration, then reconnect the MCP client.

Grant `quiqqer.payments.mcp` to the AI account. Creation additionally requires
`quiqqer.payments.create`; updates, activation, and deactivation require
`quiqqer.payments.edit`. These tools administer global payment methods.
Order-specific availability remains part of the order workflow.

Use a payment type's `name` from `payments_types` to create a disabled method.
Review its settings before activating it. Text fields accept objects keyed by
installed language codes. Updates preserve omitted fields and translations;
`paymentFee: null` explicitly removes a fee. Restriction fields use the same
comma-separated identifiers as the administration; empty strings clear them.
Dates use the server's local time as `YYYY-MM-DDTHH:MM[:SS]`.

## Technical notes

- Package name: `quiqqer/payments`
- Minimum PHP version: 8.2
- Minimum QUIQQER Core version: 2.34 (scoped permission context for MCP calls)
- Database access is provided by QUIQQER's CRUD and DBAL layers.
- Payment-type implementations extend
  `QUI\ERP\Accounting\Payments\Api\AbstractPayment`.
- Subscription-capable implementations use
  `QUI\ERP\Accounting\Payments\Types\RecurringPaymentInterface`.

### Developer events

- `onPaymentsGatewayReadRequest` (`Gateway`)
- `onPaymentsCanUsedBy` (`Payment`, `User`)
- `onPaymentCanUsedInOrder` (`Payment`, `Order`)

## Development

Initialize and run the package-local quality tools:

```bash
composer dev:init
composer test
```

## License

- GPL-3.0-or-later
- PCSG QEL-1.0

## Support

- Issues: https://dev.quiqqer.com/quiqqer/payments/-/issues
- Source: https://dev.quiqqer.com/quiqqer/payments
- Email: info@quiqqer.com
