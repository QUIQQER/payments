<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

require_once dirname(dirname(dirname(dirname(__FILE__)))).'/header.php';

use \QUI\ERP\Accounting\Payments\Gateway\Gateway;
use \Symfony\Component\HttpFoundation\RedirectResponse;
use \Symfony\Component\HttpFoundation\Response;

QUI\ERP\Debug::getInstance()->log('Gateway incoming');

try {
    QUI\ERP\Debug::getInstance()->log('Read Request');
    QUI\ERP\Debug::getInstance()->log($_GET);

    if (!isset($_GET[Gateway::URL_PARAM_USER_REDIRECTED])) {
        $_GET[Gateway::URL_PARAM_USER_REDIRECTED] = 1;
    }

    $SessionUser = QUI::getUsers()->getUserBySession();
    $Users       = QUI::getUsers();

    if ((int)$_GET[Gateway::URL_PARAM_USER_REDIRECTED] === 1
        && !$Users->isNobodyUser($SessionUser)) {
        QUI\Permissions\Permission::setUser($SessionUser);
    } else {
        define('SYSTEM_INTERN', true);

        QUI\Permissions\Permission::setUser(
            QUI::getUsers()->getSystemUser()
        );
    }

    $Gateway = new Gateway();
    $Gateway->readRequest();

    $orderUrl = $Gateway->getOrderUrl();
    $Order    = $Gateway->getOrder();

    // Bezahlung vom Gateway (payment execution from the gateway)
    if (isset($_REQUEST['GatewayPayment']) || $Gateway->isGatewayPayment()) {
        QUI\ERP\Debug::getInstance()->log('Execute Gateway Payment');
        QUI\ERP\Debug::getInstance()->log($Order->getHash());
        $Gateway->executeGatewayPayment();
        exit;
    }

    if (empty($orderUrl)) {
        QUI\System\Log::writeDebugException(new QUI\Exception(
            'No Order found in gateway request.',
            404,
            [
                'headers'   => getallheaders(),
                '$_REQUEST' => $_REQUEST
            ]
        ));

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

    try {
        $Project = QUI::getProjectManager()->getStandard();
        $url     = QUI\ERP\Order\Utils\Utils::getOrderProcessUrl($Project);
    } catch (QUI\Exception $Exception) {
        $url = URL_DIR;
    }

    $Redirect = new RedirectResponse($url);
    $Redirect->setStatusCode(Response::HTTP_SEE_OTHER);

    echo $Redirect->getContent();
    $Redirect->send();
    exit;
}
