<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Types\Payment
 */

namespace QUI\ERP\Accounting\Payments\Types;

use QUI;
use QUI\CRUD\Factory;
use QUI\Translator;
use QUI\ERP\Accounting\Payments\Api;
use QUI\Permissions\Permission;

use QUI\ERP\Areas\Utils as AreaUtils;

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
     * @return array
     */
    public function toArray()
    {
        $lg = 'quiqqer/payments';
        $id = $this->getId();

        $attributes = $this->getAttributes();
        $Locale     = QUI::getLocale();

        try {
            $availableLanguages = QUI\Translator::getAvailableLanguages();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $availableLanguages = [];
        }

        foreach ($availableLanguages as $language) {
            $attributes['title'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'payment.'.$id.'.title'
            );

            $attributes['description'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'payment.'.$id.'.description'
            );

            $attributes['workingTitle'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'payment.'.$id.'.workingTitle'
            );
        }

        $attributes['paymentType'] = false;

        try {
            $attributes['paymentType'] = $this->getPaymentType()->toArray();
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        return $attributes;
    }

    /**
     * Is the payment successful?
     * This method returns the payment success type
     *
     * @param string $hash - Vorgangsnummer - hash number - procedure number
     * @return bool
     *
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function isSuccessful($hash)
    {
        return $this->getPaymentType()->isSuccessful($hash);
    }

    /**
     * Return the payment type of the type
     *
     * @return Api\AbstractPayment
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function getPaymentType()
    {
        $type = $this->getAttribute('payment_type');

        if (!class_exists($type)) {
            throw new QUI\ERP\Accounting\Payments\Exception([
                'quiqqer/payments',
                'exception.payment.type.not.found',
                ['paymentType' => $type]
            ]);
        }

        $Type = new $type();

        if (!($Type instanceof Api\AbstractPayment)) {
            throw new QUI\ERP\Accounting\Payments\Exception([
                'quiqqer/payments',
                'exception.payment.type.not.abstractPayment',
                ['paymentType' => $type]
            ]);
        }

        return $Type;
    }

    /**
     * is the user allowed to use the discount
     *
     * @param QUI\Interfaces\Users\User $User
     * @return boolean
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User)
    {
        if ($this->isActive() === false) {
            return false;
        }

        try {
            $this->getPaymentType();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }

        // usage definitions / limits
        $dateFrom  = $this->getAttribute('date_from');
        $dateUntil = $this->getAttribute('date_until');
        $now       = time();

        if ($dateFrom && strtotime($dateFrom) > $now) {
            return false;
        }

        if ($dateUntil && strtotime($dateUntil) < $now) {
            return false;
        }

        // assignment
        $userGroupValue = $this->getAttribute('user_groups');
        $areasValue     = $this->getAttribute('areas');

        // if groups and areas are empty, everybody is allowed
        if (empty($userGroupValue) && empty($areasValue)) {
            return true;
        }

        // not in area
        $areasValue = explode($areasValue, ',');

        if (!empty($areasValue) && !AreaUtils::isUserInAreas($User, $areasValue)) {
            return false;
        }

        $userGroups = QUI\Utils\UserGroups::parseUsersGroupsString(
            $this->getAttribute('user_groups')
        );

        $discountUsers  = $userGroups['users'];
        $discountGroups = $userGroups['groups'];

        // user checking
        foreach ($discountUsers as $uid) {
            if ($User->getId() == $uid) {
                return true;
            }
        }

        // group checking
        $groupsOfUser = $User->getGroups();

        /* @var $Group QUI\Groups\Group */
        foreach ($discountGroups as $gid) {
            foreach ($groupsOfUser as $Group) {
                if ($Group->getId() == $gid) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Activate the payment type
     *
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function activate()
    {
        $this->setAttribute('active', 1);
        $this->update();
        $this->refresh();
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
     *
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function deactivate()
    {
        $this->setAttribute('active', 0);
        $this->update();
        $this->refresh();
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
            'payment.'.$this->getId().'.title'
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
            'payment.'.$this->getId().'.description'
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
            'payment.'.$this->getId().'.workingTitle'
        );
    }

    /**
     * Return the icon for the Payment
     *
     * @return string
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function getIcon()
    {
        return $this->getPaymentType()->getIcon();
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
            'payment.'.$this->getId().'.title',
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
            'payment.'.$this->getId().'.description',
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
            'payment.'.$this->getId().'.workingTitle',
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
        $data = [
            'datatype' => 'php,js',
            'package'  => 'quiqqer/payments'
        ];

        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (!isset($title[$language])) {
                continue;
            }

            $data[$language]         = $title[$language];
            $data[$language.'_edit'] = $title[$language];
        }

        $exists = Translator::getVarData('quiqqer/payments', $var, 'quiqqer/payments');

        try {
            if (empty($exists)) {
                Translator::addUserVar('quiqqer/payments', $var, $data);
            } else {
                Translator::edit('quiqqer/payments', $var, 'quiqqer/payments', $data);
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        try {
            Translator::publish('quiqqer/payments');
        } catch (QUi\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
    //endregion
}
