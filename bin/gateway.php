<?php

define('QUIQQER_SYSTEM', true);

require_once dirname(dirname(dirname(dirname(__FILE__)))).'/header.php';

use \Symfony\Component\HttpFoundation\RedirectResponse;
use \Symfony\Component\HttpFoundation\Response;

$Gateway = new QUI\ERP\Accounting\Payments\Gateway\Gateway();
$Gateway->readRequest();

$orderUrl = $Gateway->getOrderUrl();
$Order    = $Gateway->getOrder();


// Umleitung zur Bestellung
$Redirect = new RedirectResponse($orderUrl);
$Redirect->setStatusCode(Response::HTTP_SEE_OTHER);

echo $Redirect->getContent();
$Redirect->send();
exit;
