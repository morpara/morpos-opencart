<?php

namespace Opencart\Catalog\Controller\Extension\MorposGateway\Payment;

use Opencart\Extension\MorposGateway\System\Library\Morpos\Client as MorposClient;
use Opencart\Extension\MorposGateway\System\Library\Morpos\Currency;
use Opencart\Extension\MorposGateway\System\Library\Morpos\Conversation;

class MorposGateway extends \Opencart\System\Engine\Controller
{
    public function index(): string
    {
        $this->load->language('extension/morpos_gateway/payment/morpos_gateway');

        $data = [
            'confirm_url' => $this->generateUrlWithLanguage(
                'extension/morpos_gateway/payment/morpos_gateway.confirm'
            ),
            'redirect_success' => $this->generateUrlWithLanguage('checkout/success'),
            'text_title' => $this->language->get('text_title'),
            'text_description' => $this->language->get('text_description'),
            'button_confirm' => $this->language->get('button_confirm'),
            'text_redirecting' => $this->language->get('text_redirecting'),
            'text_payment_failed' => $this->language->get('text_payment_failed'),
            'text_payment_failed_default' => $this->language->get('text_payment_failed_default'),
            'text_payment_init_failed' => $this->language->get('text_payment_init_failed'),
            'text_network_error' => $this->language->get('text_network_error'),
        ];

        // Check for payment errors in session and display them
        if (isset($this->session->data['error']['payment'])) {
            $data['error_payment'] = $this->session->data['error']['payment'];
            // Clear the error after displaying it
            unset($this->session->data['error']['payment']);
        }

        return $this->load->view('extension/morpos_gateway/payment/morpos_gateway', $data);
    }

    public function confirm(): void
    {
        require_once DIR_OPENCART . 'extension/morpos_gateway/system/library/morpos/Client.php';
        require_once DIR_OPENCART . 'extension/morpos_gateway/system/library/morpos/Currency.php';
        require_once DIR_OPENCART . 'extension/morpos_gateway/system/library/morpos/Conversation.php';

        $this->load->language('extension/morpos_gateway/payment/morpos_gateway');
        $json = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }

        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'morpos_gateway.morpos_gateway') {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$this->cart->hasProducts()) {
            $json['error'] = $this->language->get('error_cart_empty');
        }

        if (!$json) {
            $this->load->model('checkout/order');

            $order_id = $this->session->data['order_id'];

            if (!$order_id) {
                $json['error'] = $this->language->get('error_order');
            }
        }

        if (!$json) {
            $order_info = $this->model_checkout_order->getOrder($order_id);

            if (!$order_info) {
                $json['error'] = $this->language->get('error_order_not_found');
            }
        }

        if (!$json) {
            // Ensure all required MorPOS gateway credentials are configured
            $required_configs = [
                'payment_morpos_gateway_client_id',
                'payment_morpos_gateway_client_secret',
                'payment_morpos_gateway_merchant_id',
                'payment_morpos_gateway_api_key'
            ];

            foreach ($required_configs as $config_key) {
                if (empty($this->config->get($config_key))) {
                    $json['error'] = $this->language->get('error_configuration_incomplete');
                    break;
                }
            }
        }

        if (!$json) {
            $client = new MorposClient(
                $this->config->get('payment_morpos_gateway_client_id'),
                $this->config->get('payment_morpos_gateway_client_secret'),
                $this->config->get('payment_morpos_gateway_merchant_id'),
                '', // submerchant_id if any
                $this->config->get('payment_morpos_gateway_api_key'),
                $this->config->get('payment_morpos_gateway_testmode') ? 'sandbox' : 'production'
            );

            $form_type = $this->config->get('payment_morpos_gateway_form_type');

            // Currency resolution: session -> order -> system default
            $selected_currency = $this->session->data['currency']
                ?? $order_info['currency_code']
                ?? $this->config->get('config_currency');

            $order_currency = $order_info['currency_code']
                ?? $this->config->get('config_currency');

            // Handle currency conversion when customer selected different currency than order
            if (strtoupper(trim($selected_currency)) !== strtoupper(trim($order_currency))) {
                $total_amount = $this->currency->format(
                    $order_info['total'],
                    $selected_currency,
                    $this->currency->getValue($selected_currency),
                    false
                );

                $payment_currency = $selected_currency;
            } else {
                // Use original order currency - no conversion needed
                $currency_value = $order_info['currency_value'];

                if (empty($currency_value) || !is_numeric($currency_value)) {
                    $json['error'] = $this->language->get('error_invalid_currency_value');
                }

                $total_amount = $this->currency->format(
                    $order_info['total'],
                    $order_currency,
                    $currency_value,
                    false
                );

                $payment_currency = $order_currency;
            }

            // Convert currency code to MorPOS numeric format
            $currency_numeric = Currency::toNumeric($payment_currency);

            if (!$currency_numeric) {
                $json['error'] = $this->language->get('error_unsupported_currency');
            }
        }

        if (!$json) {
            // Prepare callback URLs for payment success/failure handling
            $query_params = [
                'order_id' => $order_id,
                'form_type' => in_array($form_type, ['hosted', 'embedded']) ? $form_type : 'hosted',
                'language' => $this->safeGet('language'),
                'timestamp' => time(),
            ];
            $return_url = $this->url->link(
                'extension/morpos_gateway/payment/morpos_gateway.callback',
                http_build_query($query_params),
                true
            );

            $fail_url = $return_url;

            // Generate unique conversation tracking token for this payment attempt
            $this->load->model('extension/morpos_gateway/payment/morpos_conversation');
            $nextSeq = $this->model_extension_morpos_gateway_payment_morpos_conversation
                ->getNextAttemptSeq((int) $order_id);

            $secret = (string) $this->config->get('payment_morpos_gateway_client_secret', '');

            // Create cryptographically unique conversation ID for security/tracking
            $conversationId = Conversation::makeConversationId20ForAttempt((int) $order_id, $nextSeq, $secret);

            // Store payment attempt in database for later validation
            $this->model_extension_morpos_gateway_payment_morpos_conversation->addAttempt(
                (int) $order_id,
                $nextSeq,
                $conversationId,
                ['created_by' => 'catalog_confirm']
            );

            $payload = [
                'conversationId' => $conversationId,
                'paymentMethod' => $form_type === 'hosted' ? 'HOSTEDPAYMENT' : 'EMBEDDEDPAYMENT',
                'returnUrl' => $return_url,
                'failUrl' => $fail_url,
                'language' => $this->getPaymentLanguage(),
                'amount' => $total_amount,
                'currencyCode' => $currency_numeric,
            ];

            $response = $client->createPayment($payload);

            if ($response['ok']) {
                $data = isset($response['data']) ? $response['data'] : [];
                $redirectUrl = isset($data['returnUrl']) ? $data['returnUrl'] : '';
                $paymentFormContent = isset($data['paymentFormContent']) ? $data['paymentFormContent'] : '';

                // Route to appropriate payment interface based on configuration
                if ($form_type === 'hosted' && $redirectUrl) {
                    $json['redirect'] = $redirectUrl;
                } elseif ($paymentFormContent) {
                    $json['html'] = $paymentFormContent;
                } else {
                    $json['error'] = $this->language->get('error_missing_payment_data');
                }
            } else {
                $json['error'] = $response['message'] ?? $response['error']
                    ?? $this->language->get('error_gateway_generic');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function callback(): void
    {
        $this->load->language('extension/morpos_gateway/payment/morpos_gateway');
        $order_id = (int) $this->safeGet('order_id', 0);

        if (!$order_id) {
            $encrypted = $this->encryptData(json_encode([
                'route' => 'checkout/checkout',
                'error' => $this->language->get('error_order_not_found')
            ]));
            $this->response->redirect($this->generateUrlWithLanguage('extension/morpos_gateway/payment/morpos_gateway.proxy') . '&data=' . urlencode($encrypted));
            return;
        }

        $this->load->model('checkout/order');

        // Get status IDs with defaults
        $paid_status_id = (int) $this->config->get('payment_morpos_gateway_success_status_id') ?: 2;
        $fail_status_id = (int) $this->config->get('payment_morpos_gateway_failed_status_id') ?: 10;
        $form_type = $this->safeGet('form_type', 'hosted');

        // Handle the callback and get result
        $callbackResult = $this->handleCallback($order_id, $paid_status_id, $fail_status_id);

        // Determine success and error message
        $isSuccess = $callbackResult['success'];
        $errorMessage = $callbackResult['error_message'] ?? $this->language->get('error_payment_failed_generic');

        // Handle response based on form type
        if ($form_type === 'embedded') {
            // Prepare view data for embedded form
            $order = $this->model_checkout_order->getOrder($order_id);

            $viewData = [
                'status' => $isSuccess ? 'success' : 'failure',
                'order_id' => $order_id,
                'order_status' => $order ? $order['order_status'] : '',
                'redirect_url' => $isSuccess
                    ? $this->generateUrlWithLanguage('checkout/success')
                    : $this->generateUrlWithLanguage('checkout/checkout'),
                'text_processing_title' => $this->language->get('text_processing_title'),
                'text_processing_heading' => $this->language->get('text_processing_heading'),
                'text_processing_message' => $this->language->get('text_processing_message'),
                'text_redirecting_auto' => $this->language->get('text_redirecting_auto'),
            ];

            if ($isSuccess) {
                $viewData['text_payment_successful'] = $this->language->get('text_payment_successful');
            } else {
                $viewData['error_message'] = $errorMessage;
                $viewData['text_payment_failed'] = $this->language->get('text_payment_failed');
                $viewData['text_redirect_retry'] = $this->language->get('text_redirect_retry');
            }

            $this->response->setOutput(
                $this->load->view('extension/morpos_gateway/payment/morpos_gateway_result', $viewData)
            );
        } else {
            // Simple redirect for hosted form
            if ($isSuccess) {
                $this->response->redirect($this->generateUrlWithLanguage('checkout/success'));
            } else {
                // Use proxy to set session error due to SameSite cookie restrictions
                $encrypted = $this->encryptData(json_encode([
                    'route' => 'checkout/checkout',
                    'error' => $errorMessage
                ]));
                $this->response->redirect($this->generateUrlWithLanguage('extension/morpos_gateway/payment/morpos_gateway.proxy') . '&data=' . urlencode($encrypted));
            }
        }
    }

    /**
     * Determine if the payment was successful based on callback data.
     *
     * @return array Result with success flag and optional error details.
     */
    protected function isPaymentSuccessful(): array
    {
        require_once DIR_OPENCART . 'extension/morpos_gateway/system/library/morpos/Client.php';

        $resultCode = (string) $this->safeGet('ResultCode', $this->safeGet('resultCode', ''));
        $message = (string) $this->safeGet('Message', $this->safeGet('message', ''));

        // Validate initial callback response codes (B0000 = success in MorPOS)
        if ($resultCode !== 'B0000' || $message !== 'Approved') {
            return [
                'success' => false,
                'error_code' => $resultCode,
                'error_message' => !empty($message) && $message !== 'Approved'
                    ? $message
                    : $this->language->get('error_payment_failed_generic')
            ];
        }

        $conversationId = (string) $this->safeGet('ConversationId', $this->safeGet('conversationId', ''));

        if (empty($conversationId)) {
            return [
                'success' => false,
                'error_code' => 'MISSING_CONVERSATION_ID',
                'error_message' => $this->language->get('error_missing_conversation_id')
            ];
        }

        // Security: Verify payment status directly with MorPOS API (not just callback)
        $api = new MorposClient(
            $this->config->get('payment_morpos_gateway_client_id'),
            $this->config->get('payment_morpos_gateway_client_secret'),
            $this->config->get('payment_morpos_gateway_merchant_id'),
            '', // submerchant_id if any
            $this->config->get('payment_morpos_gateway_api_key'),
            $this->config->get('payment_morpos_gateway_testmode') ? 'sandbox' : 'production'
        );

        $checkResult = $api->checkPayment([
            'conversationId' => $conversationId,
        ]);

        if (!$checkResult['ok']) {
            return [
                'success' => false,
                'error_code' => 'API_ERROR',
                'error_message' => $this->language->get('error_payment_verification_failed')
            ];
        }

        // Validate API response matches callback data for consistency
        $checkData = $checkResult['data'] ?? [];
        $checkResponseCode = $checkData['responseCode'] ?? '';
        $checkResponseDescription = $checkData['responseDescription'] ?? '';

        if ($checkResponseCode !== 'B0000' || $checkResponseDescription !== 'Approved') {
            return [
                'success' => false,
                'error_code' => $checkResponseCode,
                'error_message' => !empty($checkResponseDescription) && $checkResponseDescription !== 'Approved'
                    ? $checkResponseDescription
                    : $this->language->get('error_payment_failed_generic')
            ];
        }

        return ['success' => true];
    }

    /**
     * Handle the payment callback logic.
     *
     * @param int $order_id The order ID.
     * @param int $paid_status_id The status ID for successful payments.
     * @param int $fail_status_id The status ID for failed payments.
     * @return array Result with success flag and optional error message.
     */
    protected function handleCallback(int $order_id, int $paid_status_id, int $fail_status_id): array
    {
        $this->load->model('checkout/order');
        $this->load->model(
            'extension/morpos_gateway/payment/morpos_conversation'
        );

        // Prevent duplicate processing of already completed payments
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info && (int) $order_info['order_status_id'] === $paid_status_id) {
            return [
                'success' => true,
                'already_processed' => true
            ];
        }

        $payload = $this->collectReturnParams();
        $shortInfo = $this->buildPaymentShortInfo($payload);

        // Validate conversation ID to prevent unauthorized callback attempts
        $conversationId = (string) $this->safeGet('ConversationId', $this->safeGet('conversationId', ''));
        $isValidConversation = $this->model_extension_morpos_gateway_payment_morpos_conversation
            ->conversationExistsForOrder((int) $order_id, (string) $conversationId);

        if (!$isValidConversation) {
            $errorMessage = $this->language->get('error_invalid_payment_session');

            return [
                'success' => false,
                'error_message' => $errorMessage
            ];
        }

        $paymentResult = $this->isPaymentSuccessful();
        if (!$paymentResult['success']) {
            $errorMessage = $paymentResult['error_message'];

            return [
                'success' => false,
                'error_message' => $errorMessage
            ];
        }

        $this->model_checkout_order->addHistory(
            $order_id,
            $paid_status_id,
            'MorPOS: Payment SUCCESSFUL.' . "\n" . $shortInfo,
            true
        );

        return ['success' => true];
    }

    /**
     * Set error message in session for display on checkout page.
     *
     * @param string $errorMessage The error message to display
     * @return void
     */
    protected function setSessionError(string $errorMessage): void
    {
        if (!isset($this->session->data)) {
            return;
        }

        if (!isset($this->session->data['error'])) {
            $this->session->data['error'] = [];
        }

        $this->session->data['error']['payment'] = $errorMessage;

        // Also set a more general error key that checkout templates might check
        $this->session->data['error_warning'] = $errorMessage;
    }

    /**
     * Extract all MorPOS callback parameters from request.
     * Handles both camelCase and PascalCase parameter naming conventions.
     *
     * @return array All callback parameters
     */
    protected function collectReturnParams(): array
    {
        $payload = [];

        // MorPOS callback parameter keys in both naming conventions
        $paramKeys = [
            'ResultCode',
            'resultCode',
            'Message',
            'message',
            'ConversationId',
            'conversationId',
            'PaymentId',
            'paymentId',
            'BankUniqueReferenceNumber',
            'bankUniqueReferenceNumber',
            'Amount',
            'amount',
            'Currency',
            'currency',
            'InstallmentCount',
            'installmentCount',
            'MaskedCardNumber',
            'maskedCardNumber'
        ];

        foreach ($paramKeys as $key) {
            $value = $this->safeGet($key, '');
            if (!empty($value)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Build a short info summary from payment callback parameters.
     *
     * @param array $payload The callback parameters
     * @return string Short info summary
     */
    protected function buildPaymentShortInfo(array $payload): string
    {
        require_once DIR_OPENCART . 'extension/morpos_gateway/system/library/morpos/Currency.php';

        $resultCode = $this->getPayloadValue($payload, ['ResultCode', 'resultCode'], '');
        $message = $this->getPayloadValue($payload, ['Message', 'message'], '');
        $conversationId = $this->getPayloadValue($payload, ['ConversationId', 'conversationId'], '');
        $paymentId = $this->getPayloadValue($payload, ['PaymentId', 'paymentId'], '');
        $bankRef = $this->getPayloadValue($payload, ['BankUniqueReferenceNumber', 'bankUniqueReferenceNumber'], '');
        $amountStr = $this->getPayloadValue($payload, ['Amount', 'amount'], '');
        $currencyNum = $this->getPayloadValue($payload, ['Currency', 'currency'], '');
        $installment = $this->getPayloadValue($payload, ['InstallmentCount', 'installmentCount'], '');
        $cardMasked = $this->getPayloadValue($payload, ['MaskedCardNumber', 'maskedCardNumber'], '');

        $currencyIso = $this->currencyFromNumeric($currencyNum);

        // Assemble payment details summary for order history logging
        $shortParts = [];

        if (!empty($cardMasked)) {
            $shortParts[] = 'Card: ' . $cardMasked;
        }

        if (!empty($installment)) {
            $shortParts[] = 'Installment: ' . $installment;
        }

        $shortParts[] = 'ResultCode: ' . ($resultCode ?: '—');
        $shortParts[] = 'Message: ' . ($message ?: '—');

        if (!empty($amountStr)) {
            $shortParts[] = 'Amount: ' . ($amountStr ?: '—') . ' ' . ($currencyIso ?: '—');
        }

        $shortParts[] = 'PaymentId: ' . ($paymentId ?: '—');
        $shortParts[] = 'ConversationId: ' . ($conversationId ?: '—');

        return implode("\n", $shortParts);
    }

    /**
     * Convert MorPOS numeric currency code to standard ISO alpha-3 format.
     *
     * @param string $numericCode The numeric currency code
     * @return string The ISO alpha-3 currency code or the numeric code if not found
     */
    protected function currencyFromNumeric(string $numericCode): string
    {
        if (empty($numericCode)) {
            return '';
        }

        // Reverse lookup using MorPOS Currency class mapping
        $map = Currency::numericMap();
        $reversed = array_flip($map);

        return $reversed[$numericCode] ?? $numericCode;
    }

    /**
     * Get value from payload array with multiple possible keys.
     *
     * @param array $payload The payload array
     * @param array $keys Array of possible keys to check
     * @param mixed $default Default value if none found
     * @return mixed The found value or default
     */
    protected function getPayloadValue(array $payload, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (isset($payload[$key]) && !empty($payload[$key])) {
                return $payload[$key];
            }
        }
        return $default;
    }

    /**
     * Determine payment interface language based on user preference.
     * MorPOS supports Turkish ('tr') and English ('en') interfaces.
     *
     * @return string Language code ('tr' or 'en')
     */
    private function getPaymentLanguage(): string
    {
        // Priority: URL parameter -> system config -> fallback
        $language = $this->safeGet('language')
            ?? $this->config->get('config_language')
            ?? 'en-gb';

        $languagePrefix = strtolower(substr(trim($language), 0, 2));

        // Turkish interface for Turkish locale, English for everything else
        return $languagePrefix === 'tr' ? 'tr' : 'en';
    }

    /**
     * Proxy route to set session error and redirect.
     * This solves the SameSite cookie issue with cross-site POST requests.
     * 
     * @return void
     */
    public function proxy(): void
    {
        $encrypted_data = $this->safeGet('data');

        if (!$encrypted_data) {
            $this->response->redirect($this->generateUrlWithLanguage('checkout/checkout'));
            return;
        }

        // Decrypt the data
        $decrypted = $this->decryptData($encrypted_data);

        if (!$decrypted) {
            $this->response->redirect($this->generateUrlWithLanguage('checkout/checkout'));
            return;
        }

        $data = json_decode($decrypted, true);

        if (!$data || !isset($data['route']) || !isset($data['error'])) {
            $this->response->redirect($this->generateUrlWithLanguage('checkout/checkout'));
            return;
        }

        // Set session error
        $this->setSessionError($data['error']);

        // Redirect to the specified route
        $this->response->redirect($this->generateUrlWithLanguage($data['route']));
    }

    /**
     * Encrypt data using the MorPOS API key as the secret.
     *
     * @param string $data The data to encrypt
     * @return string|null The encrypted data (base64 encoded) or null on failure
     */
    private function encryptData(string $data): ?string
    {
        $secret = $this->config->get('payment_morpos_gateway_api_key');

        if (!$secret || !function_exists('openssl_encrypt')) {
            return null;
        }

        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($data, $method, $secret, 0, $iv);

        if ($encrypted === false) {
            return null;
        }

        // Combine IV and encrypted data, then base64 encode for URL safety
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data using the MorPOS API key as the secret.
     *
     * @param string $encrypted_data The encrypted data (base64 encoded)
     * @return string|null The decrypted data or null on failure
     */
    private function decryptData(string $encrypted_data): ?string
    {
        $secret = $this->config->get('payment_morpos_gateway_api_key');

        if (!$secret || !function_exists('openssl_decrypt')) {
            return null;
        }

        $data = base64_decode($encrypted_data);
        if ($data === false) {
            return null;
        }

        $method = 'AES-256-CBC';
        $iv_length = openssl_cipher_iv_length($method);

        if (strlen($data) < $iv_length) {
            return null;
        }

        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        $decrypted = openssl_decrypt($encrypted, $method, $secret, 0, $iv);

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Generate URL with language parameter preserved.
     *
     * @param string $route The route for the URL
     * @param array $params Additional parameters
     * @param bool $secure Whether to use HTTPS
     * @return string The generated URL with language parameter
     */
    private function generateUrlWithLanguage(string $route, array $params = [], bool $secure = true): string
    {
        $language = $this->safeGet('language');
        if ($language) {
            $params['language'] = $language;
        }

        $query = !empty($params) ? http_build_query($params) : '';
        return $this->url->link($route, $query, $secure);
    }

    /**
     * Secure parameter retrieval with basic sanitization.
     * Prevents null byte injection and normalizes whitespace.
     * Checks POST parameters first, then falls back to GET parameters.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value to return if the key is not found.
     * @return mixed The sanitized value from POST or GET parameters, or the default value.
     */
    private function safeGet(string $key, $default = null)
    {
        // Check POST first, then GET
        if (isset($this->request->post[$key])) {
            $value = $this->request->post[$key];
        } elseif (isset($this->request->get[$key])) {
            $value = $this->request->get[$key];
        } else {
            return $default;
        }

        if (is_string($value)) {
            // Remove security threats and normalize whitespace
            $value = str_replace("\0", '', $value);
            $value = trim($value);
        }

        return $value;
    }
}
