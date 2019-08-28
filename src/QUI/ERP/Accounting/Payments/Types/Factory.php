<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Types\Factory
 */

namespace QUI\ERP\Accounting\Payments\Types;

use QUI;
use QUI\Permissions\Permission;

/**
 * Class Factory
 *
 * @package QUI\ERP\Accounting\Payments\Types
 */
class Factory extends QUI\CRUD\Factory
{
    /**
     * Handler constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $self = $this;

        $this->Events->addEvent('onCreateBegin', function () {
            Permission::checkPermission('quiqqer.payments.create');
        });

        // create new translation var for the area
        $this->Events->addEvent('onCreateEnd', function () use ($self) {
            QUI\Translator::publish('quiqqer/payments');
        });
    }

    /**
     * @param array $data
     *
     * @return Payment
     *
     * @throws QUI\ERP\Accounting\Payments\Exception
     * @throws QUI\Exception
     */
    public function createChild($data = [])
    {
        if (!isset($data['active']) || !\is_integer($data['active'])) {
            $data['active'] = 0;
        }

        if (!isset($data['purchase_quantity_from']) || !\is_integer($data['purchase_quantity_from'])) {
            $data['purchase_quantity_from'] = 0;
        }

        if (!isset($data['purchase_quantity_until']) || !\is_integer($data['purchase_quantity_until'])) {
            $data['purchase_quantity_until'] = 0;
        }

        if (!isset($data['priority']) || !\is_integer($data['priority'])) {
            $data['priority'] = 0;
        }

        if (!isset($data['payment_type']) || !\class_exists($data['payment_type'])) {
            throw new QUI\ERP\Accounting\Payments\Exception([
                'quiqqer/payments',
                'exception.create.payment.class.not.found'
            ]);
        }

        QUI::getEvents()->fireEvent('paymentsCreateBegin', [$data['payment_type']]);

        $payment       = $data['payment_type'];
        $PaymentMethod = new $payment();

        /* @var $PaymentMethod QUI\ERP\Accounting\Payments\Api\AbstractPayment */
        if ($PaymentMethod->isUnique()) {
            // if the payment is unique, we must check, if a payment method already exists
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $children = $Payments->getPayments([
                'where' => [
                    'payment_type' => $payment
                ]
            ]);

            if (count($children)) {
                throw new QUI\ERP\Accounting\Payments\Exception([
                    'quiqqer/payments',
                    'exception.create.unique.payment.already.exists'
                ]);
            }
        }

        /* @var $NewChild Payment */
        $NewChild = parent::createChild($data);

        $this->createPaymentLocale(
            'payment.'.$NewChild->getId().'.title',
            '[quiqqer/payments] new.payment.paceholder'
        );

        $this->createPaymentLocale(
            'payment.'.$NewChild->getId().'.workingTitle',
            '[quiqqer/payments] new.payment.paceholder'
        );

        $this->createPaymentLocale(
            'payment.'.$NewChild->getId().'.description',
            '&nbsp;'
        );

        try {
            QUI\Translator::publish('quiqqer/payments');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        QUI::getEvents()->fireEvent('paymentsCreateEnd', [$NewChild]);

        return $NewChild;
    }

    /**
     * @return string
     */
    public function getDataBaseTableName()
    {
        return 'payments';
    }

    /**
     * @return string
     */
    public function getChildClass()
    {
        return Payment::class;
    }

    /**
     * @return array
     */
    public function getChildAttributes()
    {
        return [
            'id',
            'payment_type',
            'active',
            'icon',
            'paymentFee',

            'date_from',
            'date_until',
            'purchase_quantity_from',
            'purchase_quantity_until',
            'purchase_value_from',
            'purchase_value_until',
            'priority',

            'areas',
            'articles',
            'categories',
            'user_groups'
        ];
    }

    /**
     * @param int $id
     *
     * @return Payment
     *
     * @throws QUI\Exception
     */
    public function getChild($id)
    {
        /* @var Payment $Payment */
        $Payment = parent::getChild($id);

        return $Payment;
    }

    /**
     * Creates a locale
     *
     * @param $var
     * @param $title
     */
    protected function createPaymentLocale($var, $title)
    {
        $current = QUI::getLocale()->getCurrent();

        if (QUI::getLocale()->isLocaleString($title)) {
            $parts = QUI::getLocale()->getPartsOfLocaleString($title);
            $title = QUI::getLocale()->get($parts[0], $parts[1]);
        }

        try {
            QUI\Translator::addUserVar('quiqqer/payments', $var, [
                $current   => $title,
                'datatype' => 'php,js',
                'package'  => 'quiqqer/payments'
            ]);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }
    }
}
