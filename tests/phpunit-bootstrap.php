<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

require_once __DIR__ . '/../../../../bootstrap.php';
require_once __DIR__ . '/DatabaseEnvironment.php';
require_once __DIR__ . '/Fixtures/SqlitePaymentTestCase.php';
require_once __DIR__ . '/Fixtures/TestPaymentMethod.php';
require_once __DIR__ . '/Fixtures/UniqueTestPaymentMethod.php';
require_once __DIR__ . '/Fixtures/RecurringOnlyPaymentMethod.php';
require_once __DIR__ . '/Fixtures/RecordingPaymentFactory.php';
require_once __DIR__ . '/Fixtures/RecordingPayment.php';

if (class_exists(\QUI\ERP\Order\Controls\AbstractOrderingStep::class)) {
    require_once __DIR__ . '/Fixtures/TestablePaymentStep.php';
}
