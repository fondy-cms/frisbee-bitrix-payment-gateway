<?php
/**
 * Frisbee Payment Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category        Frisbee
 * @package         frisbee
 * @version         1.0.0
 * @author          Frisbee
 * @copyright       Copyright (c) 2021 Frisbee
 * @license         http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * EXTENSION INFORMATION
 *
 * 1C-Bitrix        16.0
 * Frisbee API       https://frisbee.ua
 *
 */

require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/paysystem/manager.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/paysystemaction.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/servicerestriction.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/services/base/restrictionmanager.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/businessvalue.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/businessvalue.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/paysystem/service.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/services/paysystem/restrictions/manager.php';

IncludeModuleLangFile(__FILE__);

use Bitrix\Sale\PaySystem\Manager as SalePaySystemManager;

class frisbee_frisbee extends CModule
{
    const MODULE_ID = 'frisbee.frisbee';
    const PARTNER_NAME = 'Frisbee';
    const PARTNER_URI = 'https://frisbee.ua';

    var $MODULE_ID = 'frisbee.frisbee';
    var $PARTNER_NAME = 'Frisbee';
    var $PARTNER_URI = 'https://frisbee.ua';

    public $MODULE_GROUP_RIGHTS = 'N';

    public function __construct()
    {
        require(dirname(__FILE__).'/version.php');
        $this->MODULE_NAME = GetMessage('F_MODULE_NAME');
        $this->MODULE_DESCRIPTION = GetMessage('F_MODULE_DESC');
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->PARTNER_NAME = 'Frisbee';
        $this->PARTNER_URI = self::PARTNER_URI;
    }

    public function getPaymentSystemByCode()
    {
        return SalePaySystemManager::getByCode('frisbee');
    }

    public function createPaymentSystem()
    {
        $fields = [
            "NAME" => "Frisbee",
            "PSA_NAME" => "Frisbee",
            "CODE" => "frisbee",
            "SORT" => "200",
            "DESCRIPTION" => "Buy now pay later with Frisbee",
            "NEW_WINDOW" => "N",
            "HAVE_PAYMENT" => "Y",
            "HAVE_ACTION" => "N",
            "HAVE_RESULT" => "Y",
            "HAVE_PREPAY" => "N",
            "HAVE_PRICE" => "N",
            "HAVE_RESULT_RECEIVE" => "Y",
            "ENCODING" => "",
            "ALLOW_EDIT_PAYMENT" => "Y",
            "IS_CASH" => "N",
            "AUTO_CHANGE_1C" => "N",
            "CAN_PRINT_CHECK" => "N",
            "ENTITY_REGISTRY_TYPE" => "ORDER",
            "XML_ID" => SalePaySystemManager::generateXmlId(),
        ];

        $image = '/bitrix/modules/frisbee.frisbee/install/logo.png';
        $fields['LOGOTIP'] = CFile::MakeFileArray($image);
        $fields['LOGOTIP']['MODULE_ID'] = "sale";
        CFile::SaveForDB($fields, 'LOGOTIP', 'sale/paysystem/logotip');

        $result = SalePaySystemManager::add($fields);
        SalePaySystemManager::update($result->getId(), [
            'PAY_SYSTEM_ID' => $result->getId(),
        ]);
    }

    public function deletePaymentSystem()
    {
        $paymentSystem = $this->getPaymentSystemByCode();
        SalePaySystemManager::delete($paymentSystem['ID']);
    }

    public function DoInstall()
    {
        if (IsModuleInstalled('sale')) {
            global $APPLICATION;
            $this->InstallFiles();
            RegisterModule($this->MODULE_ID);

            if (!$this->getPaymentSystemByCode()) {
                $this->createPaymentSystem();
            }

            return true;
        }

        $MODULE_ID = $this->MODULE_ID;
        $TAG = 'VWS';
        $MESSAGE = GetMessage('F_ERR_MODULE_NOT_FOUND', array('#MODULE#'=>'sale'));
        $intID = CAdminNotify::Add(compact('MODULE_ID', 'TAG', 'MESSAGE'));

        return false;
    }

    public function DoUninstall()
    {
        global $APPLICATION;
        COption::RemoveOption($this->MODULE_ID);
        UnRegisterModule($this->MODULE_ID);

        if ($this->getPaymentSystemByCode()) {
            $this->deletePaymentSystem();
        }

        $this->UnInstallFiles();
    }

    public function InstallFiles()
    {
        CopyDirFiles(
            $this->getAbsolutePath('/bitrix/modules/'.$this->MODULE_ID.'/install/frisbee_result/'),
            $this->getAbsolutePath('/bitrix/tools/frisbee_result/'),
            true, true
        );
		CopyDirFiles(
			$_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/'.$this->MODULE_ID.'/install/handler/frisbee/',
			$_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/sale/handlers/paysystem/frisbee/',
		true, true
        );
    }

    public function UnInstallFiles()
    {
		DeleteDirFilesEx('/bitrix/tools/frisbee_result/');
		DeleteDirFilesEx("/bitrix/modules/sale/handlers/paysystem/frisbee/");
        return true;
    }

    private function getAbsolutePath($path)
    {
        return sprintf('%s/%s', $_SERVER['DOCUMENT_ROOT'], ltrim($path, '/'));
    }
}
