<?php

namespace Sale\Handlers\PaySystem;

use Bitrix\Main\Request;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Type\Date;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\PriceMaths;
use Bitrix\Sale\Registry;

Loc::loadMessages(__FILE__);

/**
 * Class FrisbeeHandler
 * @package Sale\Handlers\PaySystem
 */
class FrisbeeHandler extends PaySystem\ServiceHandler
    //implements PaySystem\IPrePayable
{
    const DELIMITER_PAYMENT_ID = ':';

    private $prePaymentSetting = array();

    /**
     * @param Payment $payment
     * @param Request|null $request
     * @return PaySystem\ServiceResult
     * @throws Main\ArgumentException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\NotImplementedException
     */
    public function initiatePay(Payment $payment, Request $request = null)
    {
        $busValues = $this->getParamsBusValue($payment);

        $order = $payment->getOrder();
        $orderId = $order->getId();

        $params = [
            'AMOUNT' => $payment->getSum() * 100,
            'CURRENCY' => $order->getCurrency(),
            'LANG' => \Bitrix\Main\Application::getInstance()->getContext()->getLanguage(),
            'MERCHANT_ID' => $busValues['MERCHANT_ID'],
            'ORDER_DESC' => $payment->getField('USER_DESCRIPTION') ?: $orderId,
            'ORDER_ID' => sprintf('%s:%s', $orderId, time()),
            'PAYMENT_SYSTEMS' => 'frisbee',
            'SENDER_EMAIL' => $order->getPropertyCollection()->getUserEmail()->getValue(),
            'SERVER_CALLBACK_URL' => $this->getPathResultUrl($payment),
        ];

        if (strtoupper($busValues['CURRENCY']) == "RUR") {
            $params['CURRENCY'] = "RUB";
        }

        $params['SIGNATURE'] = $this->getSignature($params, $busValues['SECRET_KEY']);
        $params['URL'] = $this->getPaymentUrl($params);

        $this->setExtraParams($params);

        return $this->showTemplate($payment, "template");
    }

    /**
     * @return array
     */
    public static function getIndicativeFields()
    {
        return array('SECRET_KEY', 'MERCHANT_ID');
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        $orderId = $this->getValueByTag($this->getOperationXml($request), 'order_id');
        var_dump($orderId, 'payid');exit;

        return str_replace("PAYMENT_", "", $orderId);
    }

    /**
     * @return mixed
     */
    protected function getUrlList()
    {
        return [
            'pay' => [
                self::ACTIVE_URL => 'https://api.fondy.eu/api/checkout/redirect/',
            ]
        ];
    }

    /**
     * @param Payment $payment
     * @param Request $request
     * @return PaySystem\ServiceResult
     */
    public function processRequest(Payment $payment, Request $request)
    {
        var_dump('process');exit;
        $result = new PaySystem\ServiceResult();

        if ($request->get('signature') === null || $request->get('operation_xml') === null) {
            $errorMessage = Loc::getMessage('SALE_HPS_LIQPAY_POST_ERROR');
            $result->addError(new Error($errorMessage));

            PaySystem\Logger::addError('LiqPay: processRequest: '.$errorMessage);
        }

        $status = $this->getValueByTag($this->getOperationXml($request), 'status');

        if ($this->isCorrectHash($payment, $request)) {
            if ($status === 'success' || $status === 'wait_reserve') {
                return $this->processNoticeAction($payment, $request);
            }

            if ($status === 'wait_secure') {
                return new PaySystem\ServiceResult();
            }
        } else {
            PaySystem\Logger::addError('LiqPay: processRequest: Incorrect hash');
            $result->addError(new Error('Incorrect hash'));
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return ['RUB', 'USD', 'EUR', 'UAH'];
    }

    /**
     * @param Payment $payment
     * @return mixed|string
     */
    private function getPathResultUrl(Payment $payment)
    {
        $url = sprintf('%s://%s/%s',
            stripos($_SERVER['SERVER_PROTOCOL'],'https') === 0 ? 'https' : 'http',
            $_SERVER['HTTP_HOST'],
            'bitrix/tools/frisbee_result/frisbee_result.php'
        );
        $url = $this->getBusinessValue($payment, 'RESPONSE_URL') ?: $url;

        return str_replace('&', '&amp;', $url);
    }

    /**
     * @param Payment $payment
     * @return mixed|string
     */
    private function getReturnUrl(Payment $payment)
    {
        return $this->getBusinessValue($payment, 'PAYPAL_RETURN') ?: $this->service->getContext()->getUrl();
    }

    /**
     * @param $data
     * @param $password
     * @param bool $encoded
     * @return string
     */
    private function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);

        $str = $password;
        foreach ($data as $k => $v) {
            $str .= '|'.$v;
        }

        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }

    private function getPaymentUrl($params)
    {
        $apiHost = 'https://api.fondy.eu';

        if (isset($params['IS_TEST']) && $params['IS_TEST']) {
            $apiHost = 'https://public.dev.cipsp.net';
        }

        return sprintf('%s/api/checkout/redirect/', $apiHost);
    }
}
