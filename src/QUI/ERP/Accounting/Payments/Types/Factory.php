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
        $this->Events->addEvent('onCreateEnd', function ($New) use ($self) {
//            QUI\Translator::publish('quiqqer/payments');
//
//            /* @var $New QUI\ERP\Areas\Area */
//            $self->createPaymentLocale(
//                'payment.' . $New->getId() . '.title',
//                $New->getAttribute('title')
//            );
//
//            $self->createPaymentLocale(
//                'payment.' . $New->getId() . '.description',
//                $New->getAttribute('description')
//            );
//
//            $self->createPaymentLocale(
//                'payment.' . $New->getId() . '.workingTitle',
//                $New->getAttribute('workingTitle')
//            );

            QUI\Translator::publish('quiqqer/payments');
        });
    }

    /**
     * @param array $data
     * @return Payment
     */
    public function createChild($data = array())
    {
        /* @var $NewChild Payment */
        $NewChild = parent::createChild($data);

        $this->createPaymentLocale(
            'payment.' . $NewChild->getId() . '.title',
            '[quiqqer/payments] new.payment.paceholder'
        );

        $this->createPaymentLocale(
            'payment.' . $NewChild->getId() . '.workingTitle',
            '[quiqqer/payments] new.payment.paceholder'
        );

        QUI\Translator::publish('quiqqer/payments');

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
        return array(
            'id',
            'payment_type',
            'active',

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
        );
    }

    /**
     * @param int $id
     * @return Payment
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
            QUI\Translator::addUserVar('quiqqer/payments', $var, array(
                $current   => $title,
                'datatype' => 'php,js',
                'package'  => 'quiqqer/payments'
            ));
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }
    }
}
