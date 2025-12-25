<?php

class ControllerExtensionPaymentMorposGateway extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/morpos_gateway');

        $data = array(
            'confirm_url' => $this->url->link('extension/payment/morpos_gateway/confirm', '', true),
            'redirect_success' => $this->url->link('checkout/success', '', true),
            'text_description' => $this->language->get('text_description'),
            'text_title' => $this->language->get('text_title'),
            'button_confirm' => $this->language->get('button_confirm'),
            'text_loading' => $this->language->get('text_loading'),
            'text_redirecting' => $this->language->get('text_redirecting'),
            'text_payment_failed_default' => $this->language->get('text_payment_failed_default'),
            'text_payment_init_failed' => $this->language->get('text_payment_init_failed'),
            'text_network_error' => $this->language->get('text_network_error'),
        );

        return $this->load->view('extension/payment/morpos_gateway', $data);
    }

    public function confirm()
    {
        require_once DIR_SYSTEM . 'library/morpos/Client.php';
        require_once DIR_SYSTEM . 'library/morpos/Currency.php';
        require_once DIR_SYSTEM . 'library/morpos/Conversation.php';

        $this->load->language('extension/payment/morpos_gateway');
        $json = array();

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }

        if (!isset($this->session->data['payment_method']) || $this->session->data['payment_method']['code'] != 'morpos_gateway') {
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
                '',
                $this->config->get('payment_morpos_gateway_api_key'),
                $this->config->get('payment_morpos_gateway_testmode') ? 'sandbox' : 'production'
            );

            $form_type = $this->config->get('payment_morpos_gateway_form_type');

            // Get currency and amount
            $order_currency = $order_info['currency_code'];
            $total_amount = $this->currency->format(
                $order_info['total'],
                $order_currency,
                $order_info['currency_value'],
                false
            );

            // Convert currency code to MorPOS numeric format
            $currency_numeric = MorposCurrency::toNumeric($order_currency);

            if (!$currency_numeric) {
                $json['error'] = $this->language->get('error_unsupported_currency');
            }
        }

        if (!$json) {
            // Prepare callback URLs
            $query_params = array(
                'order_id' => $order_id,
                'form_type' => in_array($form_type, array('hosted', 'embedded')) ? $form_type : 'hosted',
                'language' => $this->safeGet('language'),
                'timestamp' => time(),
            );

            $return_url = $this->url->link(
                'extension/payment/morpos_gateway/callback',
                '',
                true
            ) . '&' . http_build_query($query_params);

            $fail_url = $return_url;

            // Generate unique conversation tracking token for this payment attempt
            $this->load->model('extension/payment/morpos_conversation');
            $nextSeq = $this->model_extension_payment_morpos_conversation->getNextAttemptSeq((int) $order_id);

            $secret = (string) $this->config->get('payment_morpos_gateway_client_secret');

            // Create cryptographically unique conversation ID for security/tracking
            $conversationId = MorposConversation::makeConversationId20ForAttempt((int) $order_id, $nextSeq, $secret);

            // Store payment attempt in database for later validation
            $this->model_extension_payment_morpos_conversation->addAttempt(
                (int) $order_id,
                $nextSeq,
                $conversationId,
                array('created_by' => 'catalog_confirm')
            );

            $payload = array(
                'conversationId' => $conversationId,
                'paymentMethod' => $form_type === 'hosted' ? 'HOSTEDPAYMENT' : 'EMBEDDEDPAYMENT',
                'returnUrl' => $return_url,
                'failUrl' => $fail_url,
                'language' => $this->getPaymentLanguage(),
                'amount' => $total_amount,
                'currencyCode' => $currency_numeric,
            );

            $response = $client->createPayment($payload);

            if ($response['ok']) {
                $data = isset($response['data']) ? $response['data'] : array();
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
                $json['error'] = isset($response['message']) ? $response['message'] :
                    (isset($response['error']) ? $response['error'] :
                        $this->language->get('error_gateway_generic'));
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function callback()
    {
        $this->load->language('extension/payment/morpos_gateway');
        $order_id = (int) $this->safeGet('order_id', 0);

        if (!$order_id) {
            $encrypted = $this->encryptData(json_encode(array(
                'route' => 'checkout/checkout',
                'error' => $this->language->get('error_order_not_found')
            )));
            $this->response->redirect($this->generateUrlWithLanguage('extension/payment/morpos_gateway/proxy') . '&data=' . urlencode($encrypted));
            return;
        }

        $this->load->model('checkout/order');

        // Get status IDs with defaults
        $paid_status_id = (int) $this->config->get('payment_morpos_gateway_success_status_id');
        if (!$paid_status_id) {
            $paid_status_id = 2;
        }

        $fail_status_id = (int) $this->config->get('payment_morpos_gateway_failed_status_id');
        if (!$fail_status_id) {
            $fail_status_id = 10;
        }

        $form_type = $this->safeGet('form_type', 'hosted');

        // Handle the callback and get result
        $callbackResult = $this->handleCallback($order_id, $paid_status_id, $fail_status_id);

        // Determine success and error message
        $isSuccess = $callbackResult['success'];
        $errorMessage = isset($callbackResult['error_message'])
            ? $callbackResult['error_message']
            : $this->language->get('error_payment_failed_generic');

        // Handle response based on form type
        if ($form_type === 'embedded') {
            // For embedded form, show result page
            $order = $this->model_checkout_order->getOrder($order_id);

            $viewData = array(
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
            );

            if ($isSuccess) {
                $viewData['text_payment_successful'] = $this->language->get('text_payment_successful');
            } else {
                $viewData['error_message'] = $errorMessage;
                $viewData['text_payment_failed'] = $this->language->get('text_payment_failed');
                $viewData['text_redirect_retry'] = $this->language->get('text_redirect_retry');
            }

            $this->response->setOutput(
                $this->load->view('extension/payment/morpos_gateway_result', $viewData)
            );
        } else {
            // Simple redirect for hosted form
            if ($isSuccess) {
                $this->response->redirect($this->generateUrlWithLanguage('checkout/success'));
            } else {
                // Use proxy to set session error due to SameSite cookie restrictions
                $encrypted = $this->encryptData(json_encode(array(
                    'route' => 'checkout/checkout',
                    'error' => $errorMessage
                )));
                $this->response->redirect($this->generateUrlWithLanguage('extension/payment/morpos_gateway/proxy') . '&data=' . urlencode($encrypted));
            }
        }
    }

    /**
     * Determine if the payment was successful based on callback data.
     *
     * @return array Result with success flag and optional error details.
     */
    protected function isPaymentSuccessful()
    {
        require_once DIR_SYSTEM . 'library/morpos/Client.php';

        $resultCode = (string) $this->safeGet('ResultCode', $this->safeGet('resultCode', ''));
        $message = (string) $this->safeGet('Message', $this->safeGet('message', ''));

        // Validate initial callback response codes (B0000 = success in MorPOS)
        if ($resultCode !== 'B0000' || $message !== 'Approved') {
            return array(
                'success' => false,
                'error_code' => $resultCode,
                'error_message' => !empty($message) && $message !== 'Approved'
                    ? $message
                    : $this->language->get('error_payment_failed_generic')
            );
        }

        $conversationId = (string) $this->safeGet('ConversationId', $this->safeGet('conversationId', ''));

        if (empty($conversationId)) {
            return array(
                'success' => false,
                'error_code' => 'MISSING_CONVERSATION_ID',
                'error_message' => $this->language->get('error_missing_conversation_id')
            );
        }

        // Verify payment status directly with MorPOS API
        $api = new MorposClient(
            $this->config->get('payment_morpos_gateway_client_id'),
            $this->config->get('payment_morpos_gateway_client_secret'),
            $this->config->get('payment_morpos_gateway_merchant_id'),
            '',
            $this->config->get('payment_morpos_gateway_api_key'),
            $this->config->get('payment_morpos_gateway_testmode') ? 'sandbox' : 'production'
        );

        $checkResult = $api->checkPayment(array(
            'conversationId' => $conversationId,
        ));

        if (!$checkResult['ok']) {
            return array(
                'success' => false,
                'error_code' => 'API_ERROR',
                'error_message' => $this->language->get('error_payment_verification_failed')
            );
        }

        // Validate API response
        $checkData = isset($checkResult['data']) ? $checkResult['data'] : array();
        $checkResponseCode = isset($checkData['responseCode']) ? $checkData['responseCode'] : '';
        $checkResponseDescription = isset($checkData['responseDescription']) ? $checkData['responseDescription'] : '';

        if ($checkResponseCode !== 'B0000' || $checkResponseDescription !== 'Approved') {
            return array(
                'success' => false,
                'error_code' => $checkResponseCode,
                'error_message' => !empty($checkResponseDescription) && $checkResponseDescription !== 'Approved'
                    ? $checkResponseDescription
                    : $this->language->get('error_payment_failed_generic')
            );
        }

        return array('success' => true);
    }

    /**
     * Handle the payment callback logic.
     *
     * @param int $order_id The order ID.
     * @param int $paid_status_id The status ID for successful payments.
     * @param int $fail_status_id The status ID for failed payments.
     * @return array Result with success flag and optional error message.
     */
    protected function handleCallback($order_id, $paid_status_id, $fail_status_id)
    {
        $this->load->model('checkout/order');
        $this->load->model('extension/payment/morpos_conversation');

        // Prevent duplicate processing
        $order_info = $this->model_checkout_order->getOrder($order_id);
        if ($order_info && (int) $order_info['order_status_id'] === $paid_status_id) {
            return array(
                'success' => true,
                'already_processed' => true
            );
        }

        $payload = $this->collectReturnParams();
        $shortInfo = $this->buildPaymentShortInfo($payload);

        // Validate conversation ID to prevent unauthorized callback attempts
        $conversationId = (string) $this->safeGet('ConversationId', $this->safeGet('conversationId', ''));
        $isValidConversation = $this->model_extension_payment_morpos_conversation
            ->conversationExistsForOrder((int) $order_id, (string) $conversationId);

        if (!$isValidConversation) {
            $errorMessage = $this->language->get('error_invalid_payment_session');

            return array(
                'success' => false,
                'error_message' => $errorMessage
            );
        }

        $paymentResult = $this->isPaymentSuccessful();
        if (!$paymentResult['success']) {
            $errorMessage = $paymentResult['error_message'];

            return array(
                'success' => false,
                'error_message' => $errorMessage
            );
        }

        $this->model_checkout_order->addOrderHistory(
            $order_id,
            $paid_status_id,
            'MorPOS: Payment SUCCESSFUL.' . "\n" . $shortInfo,
            true
        );

        return array('success' => true);
    }

    /**
     * Set error message in session for display on checkout page.
     *
     * @param string $errorMessage The error message to display
     * @return void
     */
    protected function setSessionError($errorMessage)
    {
        if (!isset($this->session->data)) {
            return;
        }

        // OpenCart 3 checkout controller expects 'error' key directly
        // The checkout template will display this in an alert
        $this->session->data['error'] = $errorMessage;
    }

    /**
     * Extract all MorPOS callback parameters from request.
     * Handles both camelCase and PascalCase parameter naming conventions.
     *
     * @return array All callback parameters
     */
    protected function collectReturnParams()
    {
        $payload = array();

        // MorPOS callback parameter keys in both naming conventions
        $paramKeys = array(
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
        );

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
    protected function buildPaymentShortInfo($payload)
    {
        require_once DIR_SYSTEM . 'library/morpos/Currency.php';

        $resultCode = $this->getPayloadValue($payload, array('ResultCode', 'resultCode'), '');
        $message = $this->getPayloadValue($payload, array('Message', 'message'), '');
        $conversationId = $this->getPayloadValue($payload, array('ConversationId', 'conversationId'), '');
        $paymentId = $this->getPayloadValue($payload, array('PaymentId', 'paymentId'), '');
        $bankRef = $this->getPayloadValue($payload, array('BankUniqueReferenceNumber', 'bankUniqueReferenceNumber'), '');
        $amountStr = $this->getPayloadValue($payload, array('Amount', 'amount'), '');
        $currencyNum = $this->getPayloadValue($payload, array('Currency', 'currency'), '');
        $installment = $this->getPayloadValue($payload, array('InstallmentCount', 'installmentCount'), '');
        $cardMasked = $this->getPayloadValue($payload, array('MaskedCardNumber', 'maskedCardNumber'), '');

        $currencyIso = $this->currencyFromNumeric($currencyNum);

        // Assemble payment details summary for order history logging
        $shortParts = array();

        if (!empty($cardMasked)) {
            $shortParts[] = 'Card: ' . $cardMasked;
        }

        if (!empty($installment)) {
            $shortParts[] = 'Installment: ' . $installment;
        }

        $shortParts[] = 'ResultCode: ' . ($resultCode ? $resultCode : '—');
        $shortParts[] = 'Message: ' . ($message ? $message : '—');

        if (!empty($amountStr)) {
            $shortParts[] = 'Amount: ' . ($amountStr ? $amountStr : '—') . ' ' . ($currencyIso ? $currencyIso : '—');
        }

        $shortParts[] = 'PaymentId: ' . ($paymentId ? $paymentId : '—');
        $shortParts[] = 'ConversationId: ' . ($conversationId ? $conversationId : '—');

        return implode("\n", $shortParts);
    }

    /**
     * Convert MorPOS numeric currency code to standard ISO alpha-3 format.
     *
     * @param string $numericCode The numeric currency code
     * @return string The ISO alpha-3 currency code or the numeric code if not found
     */
    protected function currencyFromNumeric($numericCode)
    {
        if (empty($numericCode)) {
            return '';
        }

        // Reverse lookup using MorPOS Currency class mapping
        $map = MorposCurrency::numericMap();
        $reversed = array_flip($map);

        return isset($reversed[$numericCode]) ? $reversed[$numericCode] : $numericCode;
    }

    /**
     * Get value from payload array with multiple possible keys.
     *
     * @param array $payload The payload array
     * @param array $keys Array of possible keys to check
     * @param mixed $default Default value if none found
     * @return mixed The found value or default
     */
    protected function getPayloadValue($payload, $keys, $default = null)
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
    private function getPaymentLanguage()
    {
        // Priority: URL parameter -> system config -> fallback
        $language = $this->safeGet('language');
        if (!$language) {
            $language = $this->config->get('config_language');
        }
        if (!$language) {
            $language = 'en-gb';
        }

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
    public function proxy()
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
    private function encryptData($data)
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
    private function decryptData($encrypted_data)
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
    private function generateUrlWithLanguage($route, $params = array(), $secure = true)
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
    private function safeGet($key, $default = null)
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
