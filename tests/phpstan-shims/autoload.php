<?php

spl_autoload_register(static function (string $class): void {
    $prefix = 'QUI\\ERP\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/QUI/ERP/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
}, true, true);
