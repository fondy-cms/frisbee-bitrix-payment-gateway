<?php

class Frisbee
{
    public const ORDER_APPROVED = 'approved';
    public const ORDER_REJECTED = 'rejected';
    public const ORDER_SEPARATOR = ':';
    public const SIGNATURE_SEPARATOR = '|';

    /**
     * @var string
     */
    protected $merchantId;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var int
     */
    protected $orderId;

    /**
     * @var int
     */
    protected $createdAt;

    public function getMerchantId()
    {
        return $this->merchantId;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function parseOrderId($orderId)
    {
        [$this->orderId, $this->createdAt] = explode(self::ORDER_SEPARATOR, $orderId);
    }

    public function getSignature($data, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);

        $str = $this->key;
        foreach ($data as $k => $v) {
            $str .= self::SIGNATURE_SEPARATOR.$v;
        }

        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }

    /**
     * @param $response
     * @return bool
     * @throws \Exception
     */
    public function isPaymentValid($response): bool
    {
        if ($this->merchantId != $response['merchant_id']) {
            throw new Exception('An error has occurred during payment. Merchant data is incorrect.');
        }

        $responseSignature = $response['signature'];
        if (isset($response['response_signature_string'])) {
            unset($response['response_signature_string']);
        }
        if (isset($response['signature'])) {
            unset($response['signature']);
        }
        if ($this->getSignature($response) != $responseSignature) {

            throw new Exception('An error has occurred during payment. Signature is not valid.');
        }

        return true;
    }
}
