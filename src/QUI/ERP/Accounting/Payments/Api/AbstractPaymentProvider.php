<?php

/**
 * This file contains \QUI\ERP\Accounting\Payments\Api\Payment
 */

namespace QUI\ERP\Accounting\Payments\Api;

/**
 * Payment provider
 * @author www.pcsg.de (Henning Leutz)
 */
abstract class AbstractPaymentProvider
{
    /**
     * Return the payment types of the provider
     * @return list<class-string>
     */
    abstract public function getPaymentTypes(): array;

    /**
     * Return the payment types that should be created when the provider package is installed.
     *
     * Payment types created through this declaration are disabled by default. Providers must
     * opt in explicitly so optional payment types are not created unintentionally.
     *
     * Each entry can either be a payment class or a definition with initial values. Locale values
     * can be supplied as translated language maps or as QUIQQER locale strings.
     *
     * @return list<class-string|array{
     *     payment_type: class-string,
     *     title?: array<string, string>|string,
     *     workingTitle?: array<string, string>|string,
     *     description?: array<string, string>|string,
     *     orderInformation?: array<string, string>|string,
     *     priority?: int,
     *     paymentFee?: float|int|string|null,
     *     icon?: string,
     *     date_from?: string|null,
     *     date_until?: string|null,
     *     purchase_quantity_from?: int,
     *     purchase_quantity_until?: int,
     *     purchase_value_from?: float|int|string|null,
     *     purchase_value_until?: float|int|string|null,
     *     areas?: string|null,
     *     articles?: string|null,
     *     categories?: string|null,
     *     user_groups?: string|null,
     *     currencies?: string|null
     * }>
     */
    public function getPaymentTypesToCreateOnInstall(): array
    {
        return [];
    }
}
