<?php

namespace carono\yandex;

/**
 * Implementation of Merchant Web Services protocol.
 */
class Mws
{
    private $log;
    public $host;
    public $is_testing = true;
    public $shop_password;
    /**
     * @var string available values: MD5 , PKCS7
     */
    public $security_type;
    public $shop_id;
    public $currency;
    public $request_source;
    public $cert;
    public $private_key;
    public $cert_password;

    public function __construct($security_type = 'MD5', $request_source = 'php://input')
    {
        $this->security_type = $security_type;
        $this->request_source = $request_source;
        $this->log = new Log();
    }

    public function getLog()
    {
        return $this->log;
    }

    /**
     * Returns successful orders and their properties.
     *
     * @return string response from Yandex.Money in XML format
     */
    public function listOrders()
    {
        $methodName = 'listOrders';
        $this->log->info('Start ' . $methodName);
        $dateTime = Utils::formatDateForMWS(new \DateTime());
        $requestParams = [
            'requestDT' => $dateTime,
            'outputFormat' => 'XML',
            'shopId' => $this->shop_id,
            'orderCreatedDatetimeLessOrEqual' => $dateTime
        ];
        $result = $this->sendUrlEncodedRequest($methodName, $requestParams);
        $this->log->info($result);
        return $result;
    }

    /**
     * Returns refunded payments.
     *
     * @return string response from Yandex.Money in XML format
     */
    public function listReturns()
    {
        $methodName = 'listReturns';
        $this->log->info('Start ' . $methodName);
        $dateTime = Utils::formatDateForMWS(new \DateTime());
        $requestParams = [
            'requestDT' => $dateTime,
            'outputFormat' => 'XML',
            'shopId' => $this->shop_id,
            'from' => '2015-01-01T00:00:00.000Z',
            'till' => $dateTime
        ];
        $result = $this->sendUrlEncodedRequest($methodName, $requestParams);
        $this->log->info($result);
        return $result;
    }

    /**
     * Refunds a successful transfer to the Payer's account.
     *
     * @param  string|int $invoiceId transaction number of the transfer being refunded
     * @param  string $amount amount to refund to the Payer's account
     * @return string                response from Yandex.Money in XML format
     * @throws \Exception
     */
    public function returnPayment($invoiceId, $amount)
    {
        $methodName = 'returnPayment';
        $this->log->info('Start ' . $methodName);
        $dateTime = Utils::formatDate(new \DateTime());
        $requestParams = [
            'clientOrderId' => time(),
            'requestDT' => $dateTime,
            'invoiceId' => $invoiceId,
            'shopId' => $this->shop_id,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $this->currency,
            'cause' => 'Нет товара'
        ];
        $result = $this->sendXmlRequest($methodName, $requestParams);
        $this->log->info($result);
        return $result;
    }

    /**
     * Completes a successful transfer to the merchant's account. Used for deferred transfers.
     *
     * @param  string|int $orderId transaction number of the transfer being confirmed
     * @param  string $amount amount to transfer
     * @return string  response from Yandex.Money in XML format
     */
    public function confirmPayment($orderId, $amount)
    {
        $methodName = 'confirmPayment';
        $this->log->info('Start ' . $methodName);
        $dateTime = Utils::formatDate(new \DateTime());
        $requestParams = [
            'clientOrderId' => time(),
            'requestDT' => $dateTime,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => 'RUB'
        ];
        $result = $this->sendUrlEncodedRequest($methodName, $requestParams);
        $this->log->info($result);
        return $result;
    }

    /**
     * Cancels a deferred payment.
     *
     * @param  string|int $orderId transaction number of the deferred payment
     * @return string              response from Yandex.Money in XML format
     */
    public function cancelPayment($orderId)
    {
        $methodName = 'cancelPayment';
        $this->log->info('Start ' . $methodName);
        $dateTime = Utils::formatDate(new \DateTime());
        $requestParams = [
            'requestDT' => $dateTime,
            'orderId' => $orderId
        ];
        $result = $this->sendUrlEncodedRequest($methodName, $requestParams);
        $this->log->info($result);
        return $result;
    }

    /**
     * Repeats a payment using the Payer's card data (with the Payer's consent) to pay for the store's
     * products or services.
     *
     * @param  string|int $invoiceId transaction number of the transfer being repeated.
     * @param  string $amount amount to make the payment
     * @return string                response from Yandex.Money in XML format
     */
    public function repeatCardPayment($invoiceId, $amount)
    {
        $methodName = 'repeatCardPayment';
        $this->log->info('Start ' . $methodName);
        $requestParams = [
            'clientOrderId' => time(),
            'invoiceId' => $invoiceId,
            'amount' => $amount
        ];
        $result = $this->sendUrlEncodedRequest($methodName, $requestParams);
        $this->log->info($result);
        return $result;
    }

    /**
     * @param $data
     * @return bool|string
     * @throws \Exception
     */
    private function signData($data)
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
        ];
        $descriptorSpec[2] = $descriptorSpec[1];
        try {
            $opensslCommand = implode(' ', [
                'openssl',
                'smime',
                '-sign',
                '-signer ' . $this->cert,
                '-inkey ' . $this->private_key,
                '-nochain',
                '-nocerts',
                '-outform',
                'PEM',
                '-nodetach',
                '-passin',
                'pass:' . $this->cert_password
            ]);

            $this->log->info('opensslCommand: ' . $opensslCommand);
            $process = proc_open($opensslCommand, $descriptorSpec, $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Fail exec openssl');
            }
            fwrite($pipes[0], $data);
            fclose($pipes[0]);
            $pkcs7 = stream_get_contents($pipes[1]);
            $this->log->info($pkcs7);
            fclose($pipes[1]);
            $resCode = proc_close($process);
            if ($resCode !== 0) {
                $errorMsg = 'OpenSSL call failed:' . $resCode . '\n' . $pkcs7;
                $this->log->info($errorMsg);
                throw new \RuntimeException($errorMsg);
            }
            return $pkcs7;
        } catch (\Exception $e) {
            $this->log->info($e);
            throw $e;
        }
    }

    /**
     * Makes XML/PKCS#7 request.
     *
     * @param  string $paymentMethod financial method name
     * @param  array $data key-value pairs of request body
     * @return string                response from Yandex.Money in XML format
     * @throws \Exception
     */
    private function sendXmlRequest($paymentMethod, $data)
    {
        $body = '<?xml version="1.0" encoding="UTF-8"?>';
        $body .= '<' . $paymentMethod . 'Request ';
        foreach ($data AS $param => $value) {
            $body .= $param . '="' . $value . '" ';
        }
        $body .= '/>';

        return $this->sendRequest($paymentMethod, $this->signData($body), 'pkcs7-mime');
    }

    /**
     * Makes application/x-www-form-urlencoded request.
     *
     * @param  string $paymentMethod financial method name
     * @param  array $data key-value pairs of request body
     * @return string                response from Yandex.Money in XML format
     */
    private function sendUrlEncodedRequest($paymentMethod, $data)
    {
        return $this->sendRequest($paymentMethod, http_build_query($data), 'x-www-form-urlencoded');
    }

    /**
     * Автоплатеж
     *
     * @param  string|int $invoiceId transaction number of the transfer being confirmed
     * @param  string $destination Номер кошелька
     * @param  string $amount amount to transfer
     * @return string response from Yandex.Money in XML format
     */
    public function confirmDepositionByWallet($invoiceId, $amount, $destination)
    {
        $methodName = 'confirmDeposition';
        $requestParams = [
            'clientOrderId' => time(),
            'requestDT' => Utils::formatDate(new \DateTime()),
            'invoiceId' => $invoiceId,
            'destination' => $destination,
            'amount' => $amount,
            'currency' => $this->currency,
            'offerAccepted' => true,
        ];
        return $this->sendUrlEncodedRequest($methodName, array_filter($requestParams));
    }

    /**
     * Автоплатеж
     *
     * @param  string|int $invoiceId transaction number of the transfer being confirmed
     * @param  string $accountNumber Номер идентифицированного счета, полученный при привязке карты Исполнителя.
     * @param  string $cardSynonym cardSynonym    xs:string    Синоним карты, полученный при привязке карты Исполнителя.
     * @param  string $amount amount to transfer
     * @return string response from Yandex.Money in XML format
     */
    public function confirmDepositionByCard($invoiceId, $amount, $accountNumber, $cardSynonym)
    {
        $methodName = 'confirmDeposition';
        $requestParams = [
            'clientOrderId' => time(),
            'requestDT' => Utils::formatDate(new \DateTime()),
            'invoiceId' => $invoiceId,
            'destination' => $accountNumber,
            'cardSynonym' => $cardSynonym,
            'amount' => $amount,
            'currency' => $this->currency,
            'offerAccepted' => true,
        ];
        return $this->sendUrlEncodedRequest($methodName, array_filter($requestParams));
    }

    /**
     * @return string
     */
    protected function getHost()
    {
        if ($this->host) {
            return $this->host;
        }

        if ($this->is_testing) {
            return 'https://penelope-demo.yamoney.ru:8083';
        }

        return 'https://penelope.yamoney.ru';
    }

    /**
     * Sends prepared request.
     *
     * @param  string $paymentMethod financial method name
     * @param  string $requestBody prepared request body
     * @param  string $contentType HTTP Content-Type header value
     * @return string                response from Yandex.Money in XML format
     */
    private function sendRequest($paymentMethod, $requestBody, $contentType)
    {

        $this->log->info($paymentMethod . ' Request: ' . $requestBody);

        $curl = curl_init();
        $params = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => ['Content-type: application/' . $contentType],
            CURLOPT_URL => rtrim($this->getHost(), '/') . '/webservice/mws/api/' . $paymentMethod,
            CURLOPT_POST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSLCERT => $this->cert . '',
            CURLOPT_SSLKEY => $this->private_key,
            CURLOPT_SSLCERTPASSWD => $this->cert_password,
            CURLOPT_VERBOSE => 1,
            CURLOPT_POSTFIELDS => $requestBody
        ];
        echo rtrim($this->getHost(), '/') . '/webservice/mws/api/' . $paymentMethod;
        curl_setopt_array($curl, $params);
        $result = null;
        try {
            $result = curl_exec($curl);
            if (!$result) {
                trigger_error(curl_error($curl));
            }
            curl_close($curl);
        } catch (\Exception $ex) {
            echo $ex;
        }
        return $result;
    }
}
