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

        if (!isset($data['paymentFee'])) {
            $data['paymentFee'] = null;
        }

        if ($data['paymentFee'] === '' || \is_numeric($data['paymentFee'])) {
            $data['paymentFee'] = null;
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

            if (\count($children)) {
                throw new QUI\ERP\Accounting\Payments\Exception([
                    'quiqqer/payments',
                    'exception.create.unique.payment.already.exists'
                ]);
            }
        }

        $PaymentLocale        = $PaymentMethod->getLocale();
        $paymentLocaleCurrent = $PaymentLocale->getCurrent();
        $languages            = QUI\Translator::getAvailableLanguages();
        $title                = [];

        foreach ($languages as $lang) {
            $PaymentLocale->setCurrent($lang);
            $PaymentMethod->setLocale($PaymentLocale);

            $titleString = $PaymentMethod->getTitle();

            if ($PaymentLocale->isLocaleString($titleString)) {
                $titleString = $PaymentLocale->get('quiqqer/payments', 'new.payment.placeholder');
            }

            $title[$lang] = $titleString;
        }

        // Reset payment locale
        $PaymentLocale->setCurrent($paymentLocaleCurrent);
        $PaymentMethod->setLocale($PaymentLocale);

        /* @var $NewChild Payment */
        $NewChild = parent::createChild($data);

        $this->createPaymentLocale('payment.'.$NewChild->getId().'.title', $title);
        $this->createPaymentLocale('payment.'.$NewChild->getId().'.workingTitle', $title);

        $this->createPaymentLocale(
            'payment.'.$NewChild->getId().'.description',
            '&nbsp;'
        );

        if ($PaymentMethod instanceof QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment) {
            $this->createPaymentLocale(
                'payment.'.$NewChild->getId().'.orderInformation',
                '[quiqqer/payments] advanced.payment.default.text'
            );
        } else {
            $this->createPaymentLocale(
                'payment.'.$NewChild->getId().'.orderInformation',
                '&nbsp;'
            );
        }

        try {
            QUI\Translator::publish('quiqqer/payments');
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        // Set new payment method as changeable
        $Settings = QUI\ERP\Accounting\Payments\Settings::getInstance();
        $Settings->set('paymentChangeable', $NewChild->getId(), "1");

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
     * @param string|array $title
     */
    protected function createPaymentLocale($var, $title)
    {
        $current = QUI::getLocale()->getCurrent();
        $options = [
            'datatype' => 'php,js',
            'package'  => 'quiqqer/payments'
        ];

        if (\is_string($title)) {
            if (QUI::getLocale()->isLocaleString($title)) {
                $parts     = QUI::getLocale()->getPartsOfLocaleString($title);
                $languages = QUI\Translator::getAvailableLanguages();

                foreach ($languages as $language) {
                    $options[$language] = QUI::getLocale()->getByLang(
                        $language,
                        $parts[0],
                        $parts[1]
                    );
                }
            } else {
                $options[$current] = $title;
            }
        } else {
            $options = \array_merge($options, $title);
        }

        try {
            QUI\Translator::addUserVar('quiqqer/payments', $var, $options);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }
    }
}
