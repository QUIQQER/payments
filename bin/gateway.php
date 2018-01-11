<?php

define('QUIQQER_SYSTEM', true);

require_once dirname(dirname(dirname(dirname(__FILE__)))).'/header.php';

use \Symfony\Component\HttpFoundation\RedirectResponse;
use \Symfony\Component\HttpFoundation\Response;

QUI\System\Log::writeRecursive('Gateway incoming');

try {
    QUI\System\Log::writeRecursive('Reed Request');
    QUI\System\Log::writeRecursive($_GET);

    $Gateway = new QUI\ERP\Accounting\Payments\Gateway\Gateway();
    $Gateway->readRequest();

    $orderUrl = $Gateway->getOrderUrl();
    $Order    = $Gateway->getOrder();

// Bezahlung vom Gateway (payment execution from the gateway)
    if (isset($_REQUEST['GatewayPayment'])) {
        QUI\System\Log::writeRecursive('Execute Gateway Payment');
        $Gateway->executeGatewayPayment();
        exit;
    }

// Umleitung zur Bestellung
    $Redirect = new RedirectResponse($orderUrl);
    $Redirect->setStatusCode(Response::HTTP_SEE_OTHER);

    echo $Redirect->getContent();
    $Redirect->send();
    exit;
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    $Response = QUI::getGlobalResponse();
    $Response->setStatusCode(Response::HTTP_BAD_REQUEST);

    echo $Response->getContent();
    $Response->send();
    exit;
}
