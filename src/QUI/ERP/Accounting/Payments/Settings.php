<?php

/**
 * This file contains QUI\ERP\Accounting\Payments\Settings
 */

namespace QUI\ERP\Accounting\Payments;

use QUI;
use QUI\Utils\Singleton;

/**
 * Class Settings
 *
 * - saves settings
 * - return settings
 *
 * @package QUI\ERP\Accounting\Payments
 */
class Settings extends Singleton
{
    /**
     * @var null|QUI\Config
     */
    protected ?QUI\Config $Config = null;

    /**
     * Return the config object
     *
     * @return QUI\Config
     * @throws QUI\Exception
     */
    protected function getConfig(): QUI\Config
    {
        if ($this->Config !== null) {
            return $this->Config;
        }

        $Package = QUI::getPackage('quiqqer/payments');
        $Config = $Package->getConfig();

        $this->Config = $Config;

        return $this->Config;
    }

    /**
     * Return a payment setting
     *
     * @param string $section
     * @param string $key
     *
     * @return bool|string
     */
    public function get(string $section, string $key): bool|string
    {
        try {
            return $this->getConfig()->get($section, $key);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * Set a payment setting
     *
     * @param string $section
     * @param string $key
     * @param string $value
     */
    public function set(string $section, string $key, string $value): void
    {
        // @todo permissions

        try {
            $this->getConfig()->setValue($section, $key, $value);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Save the payment config
     *
     * @throws QUI\Exception
     */
    public function save(): void
    {
        // @todo permissions

        $this->getConfig()->save();
    }

    /**
     * Remove a section
     *
     * @param $section
     */
    public function removeSection($section): void
    {
        try {
            $this->getConfig()->del($section);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }
}
