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
use Bitrix\Crm\Order\Order;

Loc::loadMessages(__FILE__);

/**
 * Class FrisbeeHandler
 *
 * @package Sale\Handlers\PaySystem
 */
class FrisbeeHandler extends PaySystem\ServiceHandler
{
    const DELIMITER_PAYMENT_ID = ':';

    private $prePaymentSetting = [];

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

        try {
            $url = $this->generateFrisbeeUrl($payment, $order, $busValues);
            LocalRedirect($url);
        } catch (\Exception $exception) {
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
            $params['URL'] = $this->getPaymentUrl($busValues);

            $this->setExtraParams($params);

            return $this->showTemplate($payment, "template");
        }
    }

    /**
     * @return array
     */
    public static function getIndicativeFields()
    {
        return ['SECRET_KEY', 'MERCHANT_ID'];
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        return $request->get('orderNumber');
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

    }

    /**
     * @return array
     */
    public function getCurrencyList()
    {
        return ['RUB', 'USD', 'EUR', 'UAH'];
    }

    private function generateFrisbeeUrl(Payment $payment, Order $order, $busValues)
    {
        $orderId = $order->getId();
        $params = [
            'order_id' => sprintf('%s:%s', $orderId, time()),
            'merchant_id' => $busValues['MERCHANT_ID'],
            'order_desc' => $payment->getField('USER_DESCRIPTION') ?: "Order #$orderId",
            'amount' => $payment->getSum() * 100,
            'server_callback_url' => $this->getPathResultUrl($payment),
            'response_url' => $this->getReturnUrl($payment),
            'lang' => \Bitrix\Main\Application::getInstance()->getContext()->getLanguage(),
            'sender_email' => $order->getPropertyCollection()->getUserEmail()->getValue(),
            'payment_systems' => 'frisbee',
            'default_payment_system' => 'frisbee',
        ];

        if (strtoupper($busValues['CURRENCY']) == "RUR") {
            $params['currency'] = "RUB";
        } else {
            $params['currency'] = $order->getCurrency();
        }

        $params['signature'] = $this->getSignature($params, $busValues['SECRET_KEY']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getPaymentUrl($busValues));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request' => $params]));
        $result = json_decode(curl_exec($ch));

        if (!isset($result->response->response_status)) {
            throw new \Exception('Unsuccessful response from API');
        } elseif ($result->response->response_status == 'success') {
            return $result->response->checkout_url;
        } else {
            throw new \Exception(sprintf('Error message from the API: %s', $result->response->error_message));
        }
    }

    /**
     * @param Payment $payment
     * @return mixed|string
     */
    private function getPathResultUrl(Payment $payment)
    {
        $url = sprintf('%s://%s/%s', stripos($_SERVER['SERVER_PROTOCOL'], 'https') === 0 ? 'https' : 'http', $_SERVER['HTTP_HOST'], 'bitrix/tools/frisbee_result/frisbee_result.php');

        return str_replace('&', '&amp;', $url);
    }

    /**
     * @param Payment $payment
     * @return mixed|string
     */
    private function getReturnUrl(Payment $payment)
    {
        return $this->getBusinessValue($payment, 'RESPONSE_URL') ?: $this->service->getContext()->getUrl();
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

    private function getPaymentUrl($params, $redirect = false)
    {
        $apiHost = 'https://api.fondy.eu';

        if (isset($params['IS_TEST']) && $params['IS_TEST']) {
            $apiHost = 'https://dev2.pay.fondy.eu';
        }

        return sprintf('%s/api/checkout/%s/', $apiHost, ($redirect ? 'redirect' : 'url'));
    }
}
