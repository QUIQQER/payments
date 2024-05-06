<?php

/**
 * Log a payments error
 *
 * @param string $errMsg
 * @param string|number $errCode (optional) - Error code
 */

use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_log_ajax_logPaymentsError',
    function (
        $errMsg,
        $errCode = 'N/A'
    ) {
        $User = QUI::getUserBySession();

        $errMsg = Orthos::clear($errMsg);
        $errMsg = mb_substr($errMsg, 0, 1024);

        $errCode = Orthos::clear($errCode);
        $errCode = mb_substr($errCode, 0, 255);

        $error = "\n";
        $error .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
        $error .= "Error message: $errMsg\n";
        $error .= "Error code: $errCode\n";
        $error .= "\n";
        $error .= "Username: {$User->getName()} (#{$User->getUUID()})\n";
        $error .= "\n================================\n";

        QUI\System\Log::addError($error, [], 'payments_errors');
    },
    ['errMsg', 'errCode']
);
