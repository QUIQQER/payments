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
     * @var null
     */
    protected $Config = null;

    /**
     * Return the config object
     *
     * @return QUI\Config
     */
    protected function getConfig()
    {
        if ($this->Config !== null) {
            return $this->Config;
        }

        $Package = QUI::getPackage('quiqqer/payments');
        $Config  = $Package->getConfig();

        $this->Config = $Config;

        return $this->Config;
    }

    /**
     * Return a payment setting
     *
     * @param string $section
     * @param string $key
     *
     * @return string
     */
    public function get($section, $key)
    {
        return $this->getConfig()->get($section, $key);
    }

    /**
     * Set a payment setting
     *
     * @param string $section
     * @param string $key
     * @param string $value
     */
    public function set($section, $key, $value)
    {
        // @todo permissions

        $this->getConfig()->setValue($section, $key, $value);
    }

    /**
     * Save the payment config
     */
    public function save()
    {
        // @todo permissions

        $this->getConfig()->save();
    }

    /**
     * Remove a section
     *
     * @param $section
     */
    public function removeSection($section)
    {
        $this->getConfig()->del($section);
    }
}
