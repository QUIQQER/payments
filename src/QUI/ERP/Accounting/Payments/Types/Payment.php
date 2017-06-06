<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Types\Factory
 */

namespace QUI\ERP\Accounting\Payments\Types;

use QUI;
use QUI\CRUD\Factory;
use QUI\Translator;
use QUI\ERP\Accounting\Payments\Api;
use QUI\Permissions\Permission;

/**
 * Class Payment
 * A user created payment
 *
 * @package QUI\ERP\Accounting\Payments\Types
 */
class Payment extends QUI\CRUD\Child
{
    /**
     * Payment constructor.
     *
     * @param int $id
     * @param Factory $Factory
     */
    public function __construct($id, Factory $Factory)
    {
        parent::__construct($id, $Factory);

        $this->Events->addEvent('onDeleteBegin', function () {
            Permission::checkPermission('quiqqer.payments.delete');
        });

        $this->Events->addEvent('onSaveBegin', function () {
            Permission::checkPermission('quiqqer.payments.edit');
        });
    }

    /**
     * Return the payment as an array
     *
     * @param null $Locale
     * @return array
     */
    public function toArray($Locale = null)
    {
        $attributes = $this->getAttributes();

        $attributes['title']        = $this->getTitle($Locale);
        $attributes['workingTitle'] = $this->getWorkingTitle($Locale);
        $attributes['description']  = $this->getDescription($Locale);

        return $attributes;
    }

    /**
     * Return the payment method of the type
     *
     * @return Api\AbstractPayment
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function getPaymentMethod()
    {
        $type = $this->getAttribute('payment_method');

        if (!class_exists($type)) {
            throw new QUI\ERP\Accounting\Payments\Exception(array(
                'quiqqer/payments',
                'exception.payment.method.not.found',
                array('paymentMethod' => $type)
            ));
        }

        $Type = new $type();

        if (!($Type instanceof Api\AbstractPayment)) {
            throw new QUI\ERP\Accounting\Payments\Exception(array(
                'quiqqer/payments',
                'exception.payment.method.not.abstractPayment',
                array('paymentMethod' => $type)
            ));
        }

        return $Type;
    }

    /**
     * Activate the payment type
     */
    public function activate()
    {
        $this->setAttribute('active', 1);
        $this->update();
    }

    /**
     * Is the payment active?
     *
     * @return bool
     */
    public function isActive()
    {
        return !!$this->getAttribute('active');
    }

    /**
     * Deactivate the payment type
     */
    public function deactivate()
    {
        $this->setAttribute('active', 0);
        $this->update();
    }

    //region GETTER

    /**
     * Return the payment title
     *
     * @param null $Locale
     * @return array|string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/payments',
            'payment.' . $this->getId() . '.title'
        );
    }

    /**
     * Return the payment description
     *
     * @param null $Locale
     * @return array|string
     */
    public function getDescription($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/payments',
            'payment.' . $this->getId() . '.description'
        );
    }

    /**
     * Return the payment working title
     *
     * @param null $Locale
     * @return array|string
     */
    public function getWorkingTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/payments',
            'payment.' . $this->getId() . '.workingTitle'
        );
    }
    //endregion

    //region SETTER

    /**
     * Set the title
     *
     * @param array $titles
     */
    public function setTitle(array $titles)
    {
        $this->setPaymentLocale(
            'payment.' . $this->getId() . '.title',
            $titles
        );
    }

    /**
     * Set the description
     *
     * @param array $descriptions
     */
    public function setDescription(array $descriptions)
    {
        $this->setPaymentLocale(
            'payment.' . $this->getId() . '.description',
            $descriptions
        );
    }

    /**
     * Set the working title
     *
     * @param array $titles
     */
    public function setWorkingTitle(array $titles)
    {
        $this->setPaymentLocale(
            'payment.' . $this->getId() . '.workingTitle',
            $titles
        );
    }

    /**
     * Creates a locale
     *
     * @param string $var
     * @param array $title
     */
    protected function setPaymentLocale($var, $title)
    {
        $data = array(
            'datatype' => 'php,js',
            'package'  => 'quiqqer/payments'
        );

        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language] = $title[$language];
            }
        }

        try {
            Translator::addUserVar('quiqqer/payments', $var, $data);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        Translator::publish('quiqqer/payments');
    }
    //endregion
}
