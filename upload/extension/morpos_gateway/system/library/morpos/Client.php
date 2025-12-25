<?php

namespace Opencart\Extension\MorposGateway\System\Library\Morpos;

class Client
{
    // Base URLs
    private const SANDBOX_BASE_URL = 'https://finagopay-pf-sale-api-gateway.prp.morpara.com';
    private const PRODUCTION_BASE_URL = 'https://sale-gateway.morpara.com';

    // Endpoints
    private const EP_HOSTED_PAYMENT = '/v1/HostedPayment/HostedPaymentRedirect';
    private const EP_EMBEDDED_PAYMENT = '/v1/EmbeddedPayment/CreatePaymentForm';
    private const EP_CHECK_PAYMENT = '/v1/Payment/CheckPayment';
    private const EP_BIN_CHECK = '/v1/BinList/CheckBin';

    // Scopes
    private const SCOPE_PAYMENT = 'payment';
    private const SCOPE_PF_RW = 'pf_write pf_read';

    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $merchantId;
    private string $submerchantId;
    private string $apiKey;
    private string $environment;

    public function __construct(string $clientId, string $clientSecret, string $merchantId, string $submerchantId, string $apiKey, string $environment = 'production')
    {
        $baseUrl = $environment === 'production'
            ? self::PRODUCTION_BASE_URL
            : self::SANDBOX_BASE_URL;

        $this->environment = $environment;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->merchantId = $merchantId;
        $this->submerchantId = $submerchantId;
        $this->apiKey = $apiKey;
    }

    /**
     * Create Hosted Payment and receive redirect URL.
     * @param array $args
     * @return array
     */
    public function createPayment(array $args): array
    {
        $conversationId = (string) ($args['conversationId'] ?? $this->generateConversationId());
        $paymentMethod = (string) ($args['paymentMethod'] ?? 'HOSTEDPAYMENT');
        $paymentInstrumentType = (string) ($args['paymentInstrumentType'] ?? 'CARD');
        $language = (string) ($args['language'] ?? 'tr');
        $transactionType = (string) ($args['transactionType'] ?? 'SALE');
        $installmentCount = (int) ($args['installmentCount'] ?? 0);
        $amount = (string) $args['amount'];
        $currencyCode = (string) $args['currencyCode'];
        $returnUrl = (string) $args['returnUrl'];
        $failUrl = (string) $args['failUrl'];

        $path = $paymentMethod === 'HOSTEDPAYMENT'
            ? self::EP_HOSTED_PAYMENT
            : self::EP_EMBEDDED_PAYMENT;

        $vftFlag = false;
        $sign = $this->sign([
            $conversationId,
            $this->merchantId,
            $returnUrl,
            $failUrl,
            $paymentMethod,
            $language,
            $paymentInstrumentType,
            $transactionType,
            $vftFlag ? 'True' : 'False',
            $installmentCount,
            $amount,
            $currencyCode,
            $this->submerchantId,
            $this->apiKey,
        ]);

        $payload = [
            'merchantId' => $this->merchantId,
            'returnUrl' => $returnUrl,
            'failUrl' => $failUrl,
            'paymentMethod' => $paymentMethod,
            'paymentInstrumentType' => $paymentInstrumentType,
            'language' => $language,
            'conversationId' => $conversationId,
            'sign' => $sign,
            'transactionDetails' => [
                'transactionType' => $transactionType,
                'installmentCount' => $installmentCount,
                'amount' => $amount,
                'currencyCode' => $currencyCode,
                'vftFlag' => $vftFlag,
            ],
            'extraParameter' => [
                'pFSubMerchantId' => $this->submerchantId,
            ],
        ];

        return $this->postJson($path, $payload, self::SCOPE_PAYMENT, 'CreatePayment');
    }

    /**
     * Check payment status with MorPOS API.
     *
     * @param array $args
     * @return array
     */
    public function checkPayment(array $args = []): array
    {
        if ($this->apiKey === '') {
            return [
                'ok' => false,
                'error' => 'API Key is required',
                'http' => null,
                'body' => null,
            ];
        }

        $conversationId = $args['conversationId'];
        if (!$conversationId) {
            return [
                'ok' => false,
                'error' => 'Conversation ID is required.',
                'http' => null,
                'body' => null,
            ];
        }

        $sign = $this->sign([
            $conversationId,
            $this->merchantId,
            $this->apiKey,
        ]);

        $payload = [
            'conversationId' => $conversationId,
            'merchantId' => $this->merchantId,
            'sign' => $sign,
        ];

        return $this->postJson(self::EP_CHECK_PAYMENT, $payload, self::SCOPE_PF_RW, 'CheckPayment');
    }

    /**
     * Small connectivity check to validate credentials.
     *
     * @param array $args
     * @return array
     */
    public function makeTestConnection(array $args = []): array
    {
        if ($this->apiKey === '') {
            return [
                'ok' => false,
                'error' => 'API Key is required',
                'http' => null,
                'body' => null,
            ];
        }

        $conversationId = (string) ($args['conversationId'] ?? $this->generateConversationId());
        $bin = (string) ($args['bin'] ?? '402940');
        $language = (string) ($args['language'] ?? 'tr');

        $sign = $this->sign([
            $conversationId,
            $this->merchantId,
            $language,
            $bin,
            $this->apiKey,
        ]);

        $payload = [
            'bin' => $bin,
            'language' => $language,
            'conversationId' => $conversationId,
            'merchantId' => $this->merchantId,
            'sign' => $sign,
        ];

        $response = $this->postJson(self::EP_BIN_CHECK, $payload, self::SCOPE_PF_RW, 'TestConnection');
    
        if (!$response['ok']) {
            return ['ok' => false];
        }

        $data = $response['data'] ?? [];

        if (!isset($data['responseCode']) || !isset($data['responseDescription'])) {
            return ['ok' => false];
        }

        if ($data['responseCode'] !== 'B0000' || $data['responseDescription'] !== 'Approved') {
            return ['ok' => false];
        }

        return ['ok' => true];
    }

    /**
     * Sends a POST request with JSON payload to the specified path.
     *
     * @param string $path
     * @param array $payload
     * @param string $scope
     * @param string $logPrefix
     * @return array
     */
    private function postJson(string $path, array $payload, string $scope, string $logPrefix): array
    {
        $endpoint = $this->baseUrl . $path;
        $timestamp = $this->currentTimestampStr();
        $headers = $this->buildHeaders($timestamp, $scope);

        $args = [
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 45,
            'sslverify' => $this->environment !== 'sandbox',
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(
            fn($k, $v) => $k . ': ' . $v,
            array_keys($headers),
            $headers
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $args['sslverify']);

        $raw = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        $err = $errno ? curl_error($ch) : null;
        curl_close($ch);

        if ($err !== null) {
            return [
                'ok' => false,
                'error' => $err ?: 'cURL error',
                'http' => null,
                'body' => null,
            ];
        }

        $body = $raw ?: '';
        $data = $this->jsonDecodeAssoc($body);

        if ($this->isHttpSuccess($code) && is_array($data)) {
            return ['ok' => true, 'http' => $code, 'data' => $data];
        }

        return [
            'ok' => false,
            'http' => $code,
            'body' => $body,
            'message' => $data['Message'] ?? null,
        ];
    }

    /**
     * Builds the headers for the API request.
     *
     * @param string $timestamp
     * @param string $scope
     * @return array
     */
    private function buildHeaders(string $timestamp, string $scope): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-ClientSecret' => $this->encodeHash($this->clientSecret, $timestamp),
            'X-ClientId' => $this->clientId,
            'X-Timestamp' => $timestamp,
            'X-GrantType' => 'client_credentials',
            'X-Scope' => $scope,
        ];
    }

    /**
     * Checks if the HTTP status code indicates success (2xx).
     *
     * @param int $code
     * @return bool
     */
    private function isHttpSuccess(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }

    /**
     * Decodes JSON string into associative array.
     *
     * @param string $json
     * @return array|null
     */
    private function jsonDecodeAssoc(string $json): ?array
    {
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Returns the current timestamp in "YmdHis" format.
     *
     * @return string
     */
    protected function currentTimestampStr(): string
    {
        return gmdate('YmdHis');
    }

    /**
     * Encodes the client secret and timestamp using SHA-256 and Base64 encoding.
     *
     * @param string $clientSecret
     * @param string $timestamp
     * @return string
     */
    protected function encodeHash(string $clientSecret, string $timestamp): string
    {
        $decoded = base64_decode($clientSecret, true);
        if ($decoded === false) {
            $decoded = '';
        }

        $shaHex = hash('sha256', $decoded . $timestamp);
        return base64_encode($shaHex);
    }

    /**
     * Signs the given fields using SHA-256 and Base64 encoding.
     *
     * @param array $fields
     * @return string
     */
    protected function sign(array $fields): string
    {
        $concatenated = implode(';', array_values($fields));
        $shaBin = hash('sha256', $concatenated, true);
        $b64 = base64_encode($shaBin);
        return strtoupper($b64);
    }

    /**
     * Generates a unique conversation ID.
     *
     * @return string
     */
    protected function generateConversationId(): string
    {
        return 'MSD' . rand(10000000000000000, 99999999999999999);
    }
}
