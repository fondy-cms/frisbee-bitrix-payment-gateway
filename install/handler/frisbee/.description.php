<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\PaySystem;
use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
	die();

Loc::loadMessages(__FILE__);

$isAvailable = PaySystem\Manager::HANDLER_AVAILABLE_TRUE;

$licensePrefix = Loader::includeModule('bitrix24') ? \CBitrix24::getLicensePrefix() : '';
$portalZone = Loader::includeModule('intranet') ? CIntranetUtils::getPortalZone() : '';

$data = [
    'NAME' => 'Frisbee',
	'SORT' => 500,
	'CODES' => [
        "MERCHANT_ID" => array(
            "NAME" => Loc::getMessage("FRISBEE_MERCHANT"),
            "DESCR" => Loc::getMessage("FRISBEE_MERCHANT_DESC"),
            "VALUE" => "",
            "TYPE" => "",
            'SORT' => 100,
        ),
        "SECRET_KEY" => array(
            "NAME" => Loc::getMessage("FRISBEE_KEY"),
            "DESCR" => Loc::getMessage("FRISBEE_KEY_DESC"),
            "VALUE" => "",
            "TYPE" => "",
            'SORT' => 200,
        ),
        "RESPONSE_URL" => array(
            "NAME" => Loc::getMessage("FRISBEE_RESPONSE_URL"),
            "DESCR" => Loc::getMessage("FRISBEE_RESPONSE_URL_DESC"),
            "VALUE" => "",
            "TYPE" => "",
            'SORT' => 300,
        ),
        "CURRENCY" => array(
            "NAME" => Loc::getMessage("FRISBEE_CURRENCY"),
            "VALUE" => "CURRENCY",
            "TYPE" => "ORDER",
            'SORT' => 400,
        ),
        "IS_TEST" => array(
            "NAME" => Loc::getMessage("FRISBEE_IS_TEST"),
            "VALUE" => "",
            "TYPE" => "",
            'SORT' => 500,
        ),
	]
];
