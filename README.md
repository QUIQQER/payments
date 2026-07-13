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

## Technical notes

- Package name: `quiqqer/payments`
- Minimum PHP version: 8.2
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
