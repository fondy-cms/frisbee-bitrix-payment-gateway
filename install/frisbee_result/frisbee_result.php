<?php

define('NOT_CHECK_PERMISSIONS', true);
define('NEED_AUTH', false);

if (!require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php")) {
    die('prolog_before.php not found!');
}

require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/paysystem/manager.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/paysystemaction.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/entity.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/collectableentity.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/entitymarkerinterface.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/entitymarker.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/businessvalueproviderinterface.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/businessvalue.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/registry.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/orderbase.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/shipmentinterface.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/paymentinterface.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/orderdiscount.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/tradingplatform/order.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/order.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/shipment.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/order.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/servicerestriction.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/services/base/restriction.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/services/base/restrictionmanager.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/services/paysystem/restrictions/manager.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/internals/payment.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/payment.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/paysystem/baseservicehandler.php';
require_once $_SERVER["DOCUMENT_ROOT"] . '/bitrix/modules/sale/lib/paysystem/servicehandler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/businessvalue_persondomain.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/persontypesite.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/company.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/orderprops.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/internals/persontype.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/sale/lib/helpers/admin/businessvalue.php';
require 'Frisbee.php';

use Bitrix\Sale\PaySystem\Manager as SalePaySystemManager;
use Bitrix\Sale\Order as SaleOrder;

class FrisbeeResult
{
    /**
     * @var Frisbee $frisbee
     */
    protected $frisbee;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var array
     */
    protected $paySystem;

    public function __construct()
    {
        $this->frisbee = new Frisbee();
        $this->data = $this->getCallbackData();
        $this->paySystem = $this->getPaySystem();
        $this->parameters = $this->getPaySystemParameters($this->paySystem['ID']);
    }

    /**
     * @return array
     */
    protected function getCallbackData()
    {
        $headers = getallheaders();
        $content = file_get_contents('php://input');

        if (isset($headers['Content-Type'])) {
            switch ($headers['Content-Type']) {
                case 'application/json':
                    return json_decode($content, true);
                case 'application/xml':
                    return (array) simplexml_load_string($content, "SimpleXMLElement", LIBXML_NOCDATA);
                default:
                    return $_REQUEST;
            }
        }

        return $_REQUEST;
    }

    /**
     * @return array|false
     */
    protected function getPaySystem()
    {
        return SalePaySystemManager::getByCode('frisbee');
    }

    /**
     * @param $paySystemId
     * @return array
     */
    protected function getPaySystemParameters($paySystemId): array
    {
        $parameters = [];
        $consumerCodePersonMapping = Bitrix\Sale\BusinessValue::getConsumerCodePersonMapping();
        if (isset($consumerCodePersonMapping["PAYSYSTEM_{$paySystemId}"])) {
            foreach ($consumerCodePersonMapping["PAYSYSTEM_{$paySystemId}"] as $name => $parameter) {
                $parameter = reset($parameter);
                $parameters[$name] = $parameter['PROVIDER_VALUE'];
            }
        }

        return $parameters;
    }

    protected function findOrder($orderId)
    {
        return SaleOrder::load($orderId);
    }

    protected function getPayment(Order $order)
    {
        /**
         * @var \Bitrix\Sale\Payment $payment
         */
        foreach ($order->getPaymentCollection()->getIterator() as $payment) {
            if (!$payment->isPaid()) {
                return $payment;
            }
        }

        if (isset($payment) && $payment->isPaid()) {
            throw new \Exception(sprintf('Order %d already paid.', $order->getId()));
        }

        throw new \Exception(sprintf('Unable to find payment for order %d.', $order->getId()));
    }

    public function process()
    {
        if (!isset($this->parameters['MERCHANT_ID']) || !isset($this->parameters['SECRET_KEY'])) {
            throw new \Exception('Frisbee merchant id not set.');
        }

        if (!isset($this->parameters['SECRET_KEY'])) {
            throw new \Exception('Frisbee secret key not set.');
        }

        if (!isset($this->data['order_id'])) {
            throw new \Exception('order_id is not provided.');
        }

        $this->frisbee->setMerchantId($this->parameters['MERCHANT_ID']);
        $this->frisbee->setKey($this->parameters['SECRET_KEY']);
        $this->frisbee->parseOrderId($this->data['order_id']);

        $orderId = $this->frisbee->getOrderId();

        /**
         * @var \Bitrix\Sale\Order $order
         */
        $order = $this->findOrder($orderId);
        $frisbeeResult = $this->frisbee->isPaymentValid($this->data);

        if ($this->data['order_status'] != Frisbee::ORDER_APPROVED) {
            $answer = 'declined';
        } elseif ($frisbeeResult == true) {
            $answer = 'OK';
            $order->getPaymentCollection()->offsetGet(0)->setPaid('Y');
        } else {
            $answer = $frisbeeResult;
        }

        if ($order) {
            $arFields = array(
                'STATUS_ID' => $answer == 'OK' ? 'P' : 'N',
                'PAYED' => $answer == 'OK' ? 'Y' : 'N',
                'PS_STATUS' => $answer == 'OK' ? 'Y' : 'N',
                'PS_STATUS_CODE' => $this->data['order_status'],
                'PS_STATUS_DESCRIPTION' => $this->data['order_status'] . ' ' . $this->paySystem['ID'] . ' ' .
                    ($answer != 'OK' ? $this->data['response_description'] : ''),
                'PS_STATUS_MESSAGE' => $this->data['order_status'],
                'PS_SUM' => $this->data['amount'],
                'PS_CURRENCY' => $this->data['currency'],
                'PS_RESPONSE_DATE' => date('m/d/Y h:i:s a'),
            );

            if ($this->data['order_status'] === Frisbee::ORDER_REJECTED) {
                $arFields['CANCELED'] = 'Y';
                $arFields['DATE_CANCELED'] = date("m/d/Y h:i:s a");
            }

            CSaleOrder::Update($orderId, $arFields);
        }

        echo $answer . "<script>window.location.replace('/personal/orders/');</script>";
    }
}

$frisbeeResult = new FrisbeeResult();
$frisbeeResult->process();

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
