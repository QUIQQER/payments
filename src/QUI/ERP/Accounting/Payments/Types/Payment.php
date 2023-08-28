<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Types\Payment
 */

namespace QUI\ERP\Accounting\Payments\Types;

use QUI;
use QUI\CRUD\Factory;
use QUI\ERP\Accounting\Payments\Api;
use QUI\ERP\Accounting\Payments\Exceptions\PaymentCanNotBeUsed;
use QUI\ERP\Areas\Utils as AreaUtils;
use QUI\ERP\BankAccounts\Handler as BankAccountsHandler;
use QUI\Exception;
use QUI\Permissions\Permission;
use QUI\Translator;

use function array_filter;
use function class_exists;
use function count;
use function explode;
use function floatval;
use function in_array;
use function is_double;
use function is_float;
use function is_string;
use function strtotime;
use function time;

/**
 * Class Payment
 * A user created payment
 *
 * @package QUI\ERP\Accounting\Payments\Types
 */
class Payment extends QUI\CRUD\Child implements PaymentInterface
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

            $paymentFee = $this->getAttribute('paymentFee');

            if ($paymentFee && !is_float($paymentFee) && !is_double($paymentFee)) {
                $paymentFee = QUI\ERP\Money\Price::validatePrice($paymentFee);

                $this->setAttribute('paymentFee', $paymentFee);
            } else {
                $this->setAttribute('paymentFee', null);
            }
        });
    }

    /**
     * Return the payment as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $lg = 'quiqqer/payments';
        $id = $this->getId();

        $attributes = $this->getAttributes();
        $Locale = QUI::getLocale();
        $availableLanguages = QUI\Translator::getAvailableLanguages();

        foreach ($availableLanguages as $language) {
            $attributes['title'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'payment.' . $id . '.title'
            );

            $attributes['description'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'payment.' . $id . '.description'
            );

            $attributes['workingTitle'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'payment.' . $id . '.workingTitle'
            );

            $attributes['orderInformation'][$language] = $Locale->getByLang(
                $language,
                $lg,
                'payment.' . $id . '.orderInformation'
            );
        }

        // payment type
        $attributes['id'] = $id;
        $attributes['priority'] = (int)$attributes['priority'];
        $attributes['active'] = (int)$attributes['active'];
        $attributes['paymentType'] = false;

        try {
            $attributes['paymentType'] = $this->getPaymentType()->toArray();
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // icon
        $attributes['icon'] = '';

        try {
            $attributes['icon'] = $this->getIcon();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
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
    public function isSuccessful(string $hash): bool
    {
        return $this->getPaymentType()->isSuccessful($hash);
    }

    /**
     * Return the payment type of the type
     *
     * @return Api\AbstractPayment
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function getPaymentType(): Api\AbstractPayment
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
     * is the user allowed to use this payment
     *
     * @param QUI\Interfaces\Users\User $User
     * @return boolean
     */
    public function canUsedBy(QUI\Interfaces\Users\User $User): bool
    {
        if ($this->isActive() === false) {
            return false;
        }

        try {
            QUI::getEvents()->fireEvent('quiqqerPaymentCanUsedBy', [$this, $User]);
        } catch (PaymentCanNotBeUsed $Exception) {
            return false;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return false;
        }


        try {
            $this->getPaymentType();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }

        // usage definitions / limits
        $dateFrom = $this->getAttribute('date_from');
        $dateUntil = $this->getAttribute('date_until');
        $now = time();

        if ($dateFrom && strtotime($dateFrom) > $now) {
            return false;
        }

        if ($dateUntil && strtotime($dateUntil) < $now) {
            return false;
        }


        // assignment
        $userGroupValue = $this->getAttribute('user_groups');
        $areasValue = $this->getAttribute('areas');

        // if groups and areas are empty, everybody is allowed
        if (empty($userGroupValue) && empty($areasValue)) {
            return true;
        }

        // not in area
        if (!empty($areasValue)) {
            $areasValue = explode(',', $areasValue);
            $areasValue = array_filter($areasValue);

            if (!AreaUtils::isUserInAreas($User, $areasValue)) {
                return false;
            }
        }

        $userGroups = QUI\Utils\UserGroups::parseUsersGroupsString(
            $this->getAttribute('user_groups')
        );

        $discountUsers = $userGroups['users'];
        $discountGroups = $userGroups['groups'];

        if (empty($discountUsers) && empty($discountGroups)) {
            return true;
        }

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
     * is the payment allowed in the order?
     *
     * @param QUI\ERP\Order\OrderInterface $Order
     * @return bool
     */
    public function canUsedInOrder(QUI\ERP\Order\OrderInterface $Order): bool
    {
        // currencies
        $currencies = $this->getAttribute('currencies');

        if (!empty($currencies)) {
            try {
                $Config = QUI::getPackage('quiqqer/payments')->getConfig();
                $listUnsupportedPayment = !!$Config->getValue('payments', 'listUnsupportedPayment');

                if ($listUnsupportedPayment === false) {
                    $currencies = explode(',', $currencies);
                    $currencies = array_filter($currencies);
                    $OrderCurrency = $Order->getCurrency();

                    if (!in_array($OrderCurrency->getCode(), $currencies)) {
                        return false;
                    }
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        try {
            QUI::getEvents()->fireEvent('paymentsCanUsedInOrder', [$this, $Order]);
            QUI::getEvents()->fireEvent('quiqqerPaymentCanUsedInOrder', [$this, $Order]);
        } catch (PaymentCanNotBeUsed $Exception) {
            return false;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return false;
        }

        return true;
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
    public function isActive(): bool
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
     * @param null|QUI\Locale $Locale
     * @return array|string
     */
    public function getTitle(QUI\Locale $Locale = null): string
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
     * @param null|QUI\Locale $Locale
     * @return array|string
     */
    public function getDescription(QUI\Locale $Locale = null): string
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
     * @param QUI\Locale|null $Locale
     * @return array|string
     */
    public function getWorkingTitle(QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/payments',
            'payment.' . $this->getId() . '.workingTitle'
        );
    }

    /**
     * Return the extra text for the invoice
     *
     * @param QUI\ERP\Order\OrderInterface $Order
     * @return string
     */
    public function getOrderInformationText(QUI\ERP\Order\OrderInterface $Order): string
    {
        $Shipping = $Order->getShipping();
        $Locale = QUI::getLocale();
        $Currency = $Order->getCurrency();

        $id = $this->getId();

        $shipping = '';
        $paidDate = '';
        $paid = '';
        $toPay = '';

        try {
            $paidStatus = $Order->getPaidStatusInformation();

            $paidDate = $paidStatus['paidDate'];
            $paid = $paidStatus['paid'];
            $toPay = $paidStatus['toPay'];
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        if ($Shipping) {
            $shipping = $Shipping->getTitle();
        }

        $Config = QUI::getPackage('quiqqer/erp')->getConfig();
        $defaultBankAccount = BankAccountsHandler::getDefaultBankAccount();

        return $Locale->get('quiqqer/payments', 'payment.' . $id . '.orderInformation', [
            'orderId' => $Order->getIdPrefix() . $Order->getId(),
            'shipping' => $shipping,
            'paidDate' => $paidDate,
            'paid' => $Currency->format($paid),
            'toPay' => $Currency->format($toPay),
            'bankName' => $defaultBankAccount ? $defaultBankAccount['name'] : '',
            'bankIban' => $defaultBankAccount ? $defaultBankAccount['iban'] : '',
            'bankBic' => $defaultBankAccount ? $defaultBankAccount['bic'] : '',
            'company' => $Config->get('company', 'name') ?: ''
        ]);
    }

    /**
     *  Return the icon for the Payment
     *
     * @return string - image url
     * @throws QUI\ERP\Accounting\Payments\Exception
     */
    public function getIcon(): string
    {
        if (!QUI\Projects\Media\Utils::isMediaUrl($this->getAttribute('icon'))) {
            return $this->getPaymentType()->getIcon();
        }

        try {
            $Image = QUI\Projects\Media\Utils::getImageByUrl(
                $this->getAttribute('icon')
            );

            return $Image->getSizeCacheUrl();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return $this->getPaymentType()->getIcon();
    }

    /**
     * @return QUI\ERP\Currency\Currency[]
     */
    public function getSupportedCurrencies(): array
    {
        // currencies
        $currencies = $this->getAttribute('currencies');
        $allowedCurrencies = QUI\ERP\Currency\Handler::getAllowedCurrencies();

        if (empty($currencies)) {
            return $allowedCurrencies;
        }

        $isInAllowedCurrencies = function ($WantedCurrency) use ($allowedCurrencies) {
            foreach ($allowedCurrencies as $Currency) {
                if ($Currency->getCode() === $WantedCurrency->getCode()) {
                    return true;
                }
            }

            return false;
        };

        $currencies = explode(',', $currencies);
        $currencies = array_filter($currencies);

        $result = [];

        foreach ($currencies as $currencyCode) {
            try {
                $Currency = QUI\ERP\Currency\Handler::getCurrency($currencyCode);

                if ($isInAllowedCurrencies($Currency)) {
                    $result[] = $Currency;
                }
            } catch (QUI\Exception $exception) {
            }
        }

        if (empty($result)) {
            return $allowedCurrencies;
        }

        return $result;
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
     * Set the order information text
     *
     * @param array $orderInformation
     */
    public function setOrderInformation(array $orderInformation)
    {
        $this->setPaymentLocale(
            'payment.' . $this->getId() . '.orderInformation',
            $orderInformation
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
     * @param string $icon - image.php?
     */
    public function setIcon($icon)
    {
        if (QUI\Projects\Media\Utils::isMediaUrl($icon)) {
            $this->setAttribute('icon', $icon);
        }
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
            'package' => 'quiqqer/payments'
        ];

        $languages = QUI::availableLanguages();
        $Locale = QUI::getLocale();

        foreach ($languages as $language) {
            if (!isset($title[$language])) {
                continue;
            }

            $str = $title[$language];

            if (QUI::getLocale()->isLocaleString($str)) {
                $parts = $Locale->getPartsOfLocaleString($str);

                if (count($parts) === 2) {
                    $data[$language] = $Locale->getByLang($language, $parts[0], $parts[1]);
                    $data[$language . '_edit'] = $Locale->getByLang($language, $parts[0], $parts[1]);
                } else {
                    $data[$language] = $title[$language];
                    $data[$language . '_edit'] = $title[$language];
                }
            } else {
                $data[$language] = $title[$language];
                $data[$language . '_edit'] = $title[$language];
            }
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

    //region payment free

    /**
     * Set the payment fee title
     *
     * @param array $titles
     */
    public function setPaymentFeeTitle(array $titles)
    {
        $this->setPaymentLocale(
            'payment.' . $this->getId() . '.paymentFeeTitle',
            $titles
        );
    }

    /**
     * Clears the payment fee
     */
    public function clearPaymentFee()
    {
        $this->setAttribute('paymentFee', false);
    }

    /**
     * Set the payment fee title
     *
     * @param string|float $paymentFee
     */
    public function setPaymentFee($paymentFee)
    {
        if (is_string($paymentFee)) {
            $paymentFee = floatval($paymentFee);
        }

        if (!is_float($paymentFee) && !is_double($paymentFee)) {
            return;
        }

        $this->setAttribute('paymentFee', $paymentFee);
    }

    /**
     * Has the payment a payment fee?
     *
     * @return bool
     */
    public function hasPaymentFee(): bool
    {
        $paymentFee = $this->getAttribute('paymentFee');

        if (empty($paymentFee)) {
            return false;
        }

        return !empty(floatval($paymentFee));
    }

    /**
     * Return the payment fee
     *
     * @return float|int
     */
    public function getPaymentFee()
    {
        $paymentFee = $this->getAttribute('paymentFee');

        if (empty($paymentFee)) {
            return 0;
        }

        return floatval($paymentFee);
    }

    /**
     * Return the payment fee title / text
     *
     * @param null|QUI\Locale $Locale
     * @return array|string
     */
    public function getPaymentFeeTitle(QUI\Locale $Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get(
            'quiqqer/payments',
            'payment.' . $this->getId() . '.paymentFeeTitle'
        );
    }

    public function toPriceFactor(
        $Locale = null,
        QUI\ERP\Order\AbstractOrder $Order = null
    ): QUI\ERP\Products\Utils\PriceFactor {
        $Currency = QUI\ERP\Defaults::getCurrency();

        if ($Order) {
            $Currency = $Order->getCurrency();
        }

        return new QUI\ERP\Products\Utils\PriceFactor([
            'title' => $this->getPaymentFeeTitle($Locale),
            'description' => '',
            'priority' => 1,
            'calculation' => QUI\ERP\Accounting\Calc::CALCULATION_COMPLEMENT,
            'basis' => QUI\ERP\Accounting\Calc::CALCULATION_BASIS_CURRENTPRICE,
            'value' => $this->getPaymentFee(),
            'visible' => true,
            'currency' => $Currency->getCode()
        ]);
    }

    /**
     * Return the price display
     *
     * @return string
     */
    public function getPaymentFeeDisplay(): string
    {
        if (!$this->hasPaymentFee()) {
            return '';
        }

        $paymentFee = $this->getPaymentFee();
        $Order = $this->getAttribute('Order');
        $isNetto = false;

        if ($Order instanceof QUI\ERP\Order\AbstractOrder) {
            $Customer = $Order->getCustomer();
            $isNetto = $Customer->isNetto();
        }

        // display is incl vat
        $Calc = $Order->getPriceCalculation();
        $vatArray = $Calc->getVat();
        $VatEntry = reset($vatArray);

        /* @var QUI\ERP\Accounting\CalculationVatValue $VatEntry */
        $vat = $VatEntry->getVat();

        if (!$isNetto && $vat) {
            $paymentFee = $paymentFee + ($paymentFee * ($vat / 100));
        }


        // if user currency is different to the default, we have to convert the price
        $DefaultCurrency = QUI\ERP\Defaults::getCurrency();
        $UserCurrency = QUI\ERP\Defaults::getUserCurrency();

        if ($DefaultCurrency->getCode() !== $UserCurrency->getCode()) {
            try {
                $price = $DefaultCurrency->convert($paymentFee, $UserCurrency);
                $Price = new QUI\ERP\Money\Price($price, $UserCurrency);
            } catch (Exception $exception) {
                $Price = new QUI\ERP\Money\Price($paymentFee, $DefaultCurrency);
            }
        } else {
            $Price = new QUI\ERP\Money\Price($paymentFee, $DefaultCurrency);
        }

        return '+' . $Price->getDisplayPrice();
    }

    //endregion
}
