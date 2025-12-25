<?php

namespace Opencart\Admin\Controller\Extension\MorposGateway\Payment;

class MorposGateway extends \Opencart\System\Engine\Controller
{
    private $error = [];

    public function index(): void
    {
        $this->load->language('extension/morpos_gateway/payment/morpos_gateway');
        $this->document->setTitle($this->language->get('heading_title'));

        $token = $this->session->data['user_token'];
        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $token)
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=payment')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/morpos_gateway/payment/morpos_gateway', 'user_token=' . $token)
        ];

        $data['user_token'] = $token;
        $data['save'] = $this->url->link(
            'extension/morpos_gateway/payment/morpos_gateway.save',
            'user_token=' . $token
        );
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $token . '&type=payment');

        $this->load->model('setting/setting');

        $fields = [
            'payment_morpos_gateway_status',
            'payment_morpos_gateway_testmode',
            'payment_morpos_gateway_sort_order',
            'payment_morpos_gateway_client_id',
            'payment_morpos_gateway_client_secret',
            'payment_morpos_gateway_merchant_id',
            'payment_morpos_gateway_api_key',
            'payment_morpos_gateway_form_type',
            'payment_morpos_gateway_success_status_id',
            'payment_morpos_gateway_failed_status_id',
            'payment_morpos_gateway_connection_status',
        ];

        foreach ($fields as $f) {
            $data[$f] = $this->config->get($f);
        }

        $data['form_types'] = [
            [
                'value' => 'hosted',
                'text' => $this->language->get('text_hosted'),
            ],
            [
                'value' => 'embedded',
                'text' => $this->language->get('text_embedded'),
            ]
        ];

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['error_warning'] = $this->error['warning'] ?? '';
        $data['requirements'] = $this->getRequirementsInfo();
        $data['morpos_logo'] = '/extension/morpos_gateway/catalog/view/image/morpos-logo.png';

        // Add missing template variables
        $data['button_save'] = $this->language->get('button_save');
        $data['button_back'] = $this->language->get('button_back');
        $data['method_description'] = $this->language->get('method_description');
        $data['morpos_gateway_version'] = $this->language->get('morpos_gateway_version');

        $this->response->setOutput($this->load->view('extension/morpos_gateway/payment/morpos_gateway', $data));
    }

    /**
     * Saves the settings via AJAX.
     *
     * @return void
     */
    public function save(): void
    {
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
            return;
        }

        $this->load->language('extension/morpos_gateway/payment/morpos_gateway');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/morpos_gateway/payment/morpos_gateway')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        $connectionResult = [];
        if (!$json) {
            // Test connection before saving
            $connectionResult = $this->performConnectionTest($this->request->post);
            $connectionStatus = $connectionResult['success'] ? 'ok' : 'fail';
            $json['connection_status'] = $connectionStatus;
            $json['connection_message'] = $connectionResult['success'] ?
                $connectionResult['message'] : $connectionResult['error'];

            // Update payment status based on connection result
            $this->request->post['payment_morpos_gateway_connection_status'] = $connectionStatus;

            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_morpos_gateway', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * AJAX handler for testing the gateway connection.
     *
     * @return void
     */
    public function testConnection(): void
    {
        if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
            $this->response->addHeader('HTTP/1.1 405 Method Not Allowed');
            return;
        }

        $this->load->language('extension/morpos_gateway/payment/morpos_gateway');

        $json = [];

        // Permission check
        if (!$this->user->hasPermission('modify', 'extension/morpos_gateway/payment/morpos_gateway')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $connectionResult = $this->performConnectionTest($this->request->post);

            if ($connectionResult['success']) {
                $json['status'] = 'ok';
                $json['success'] = $connectionResult['message'];
            } else {
                $json['status'] = 'fail';
                $json['error'] = $connectionResult['error'];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Performs connection test to MorPOS Gateway API.
     *
     * @param array $postData The POST data containing credentials and settings
     * @return array Array with 'success' boolean, 'message' or 'error' string
     */
    private function performConnectionTest(array $postData): array
    {
        // Extract credentials from POST data
        $credentials = [];
        $fields = ['merchant_id', 'client_id', 'client_secret', 'api_key'];

        if (isset($postData['credentials']) && is_array($postData['credentials'])) {
            // Handle nested credentials array format
            foreach ($fields as $field) {
                $credentials[$field] = $postData['credentials'][$field] ?? '';
            }
        } else {
            // Handle direct POST data format - try both prefixed and non-prefixed keys
            foreach ($fields as $field) {
                $prefixedKey = 'payment_morpos_gateway_' . $field;

                // Try prefixed key first, then fallback to non-prefixed key
                if (isset($postData[$prefixedKey]) && !empty($postData[$prefixedKey])) {
                    $credentials[$field] = $postData[$prefixedKey];
                } elseif (isset($postData[$field]) && !empty($postData[$field])) {
                    $credentials[$field] = $postData[$field];
                } else {
                    $credentials[$field] = '';
                }
            }
        }

        // Validate required fields
        $isValid = array_reduce($fields, fn ($carry, $f) => $carry && !empty($credentials[$f]), true);
        if (!$isValid) {
            return [
                'success' => false,
                'error' => $this->language->get('error_please_fill_all_fields')
            ];
        }

        // Determine test mode - handle both formats
        $testmode = false;
        if (isset($postData['testmode'])) {
            $testmode = in_array($postData['testmode'], ['yes', '1', 1], true);
        } elseif (isset($postData['payment_morpos_gateway_testmode'])) {
            $testmode = in_array($postData['payment_morpos_gateway_testmode'], ['1', 1, 'on'], true);
        }

        try {
            // Attempt connection to MorPOS Gateway API
            require_once DIR_OPENCART . 'extension/morpos_gateway/system/library/morpos/Client.php';

            // Create client instance
            $client = new \Opencart\Extension\MorposGateway\System\Library\Morpos\Client(
                $credentials['client_id'],
                $credentials['client_secret'],
                $credentials['merchant_id'],
                '',
                $credentials['api_key'],
                $testmode ? 'sandbox' : 'production'
            );

            $response = $client->makeTestConnection();

            $this->load->model('setting/setting');

            // Update payment status based on connection result
            $settings = $this->model_setting_setting->getSetting('payment_morpos_gateway');
            $settings['payment_morpos_gateway_connection_status'] = $response['ok'] === true ? 'ok' : 'fail';
            $this->model_setting_setting->editSetting('payment_morpos_gateway', $settings);

            if ($response['ok']) {
                return [
                    'success' => true,
                    'message' => $this->language->get('text_connection_successful')
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $this->language->get('text_connection_failed')
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $this->language->get('text_connection_failed') . ': ' . $e->getMessage()
            ];
        }
    }

    protected function validate(): bool
    {
        if (!$this->user->hasPermission('modify', 'extension/morpos_gateway/payment/morpos_gateway')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    /**
     * Called when the extension is installed via admin Extensions -> Install.
     */
    public function install(): void
    {
        $this->load->model('extension/morpos_gateway/payment/morpos_gateway');
        $this->model_extension_morpos_gateway_payment_morpos_gateway->createTable();

        // Configure OpenCart for optimal payment gateway operation
        $this->configureOpenCartForPaymentGateways();
    }

    /**
     * Configure OpenCart settings for payment gateway compatibility
     * This ensures sessions work properly with external payment redirects
     */
    private function configureOpenCartForPaymentGateways(): void
    {
        $this->load->model('setting/setting');

        // Get current config
        $settings = $this->model_setting_setting->getSetting('config');
        $changed = false;

        // Set session SameSite policy to Lax for payment gateway compatibility
        if (!isset($settings['config_session_samesite']) || $settings['config_session_samesite'] === 'Strict') {
            $settings['config_session_samesite'] = 'Lax';
            $changed = true;
            $this->log->write('MorPOS Gateway: Updated session SameSite policy to Lax for payment gateways');
        }

        // Save the updated settings if changed
        if ($changed) {
            $this->model_setting_setting->editSetting('config', $settings);
        }
    }

    /**
     * Called when the extension is uninstalled via admin Extensions -> Uninstall.
     */
    public function uninstall(): void
    {
        $this->load->model('extension/morpos_gateway/payment/morpos_gateway');
        $this->model_extension_morpos_gateway_payment_morpos_gateway->dropTable();
    }

    /**
     * Detects the TLS capability of the server.
     *
     * @return array
     */
    protected function detectTlsCapability(): array
    {
        $openssl_text = defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null;
        $openssl_num = defined('OPENSSL_VERSION_NUMBER') ? OPENSSL_VERSION_NUMBER : null;
        $curl_info = function_exists('curl_version') ? curl_version() : null;

        // Derive minimal TLS supported via OpenSSL version heuristic
        // - < 1.0.1  → effectively no TLS 1.2
        // - 1.0.1+   → TLS 1.2 available
        // - 1.1.1+   → TLS 1.3 available (if backend supports)
        $min_tls = 'unknown';
        if ($openssl_num) {
            if ($openssl_num < 0x1000100f) {         // < 1.0.1
                $min_tls = '1.0';
            } elseif ($openssl_num < 0x1010100f) {   // < 1.1.1
                $min_tls = '1.2';
            } else {
                $min_tls = '1.3';
            }
        }

        $label_parts = [];
        if ($openssl_text) {
            $label_parts[] = $openssl_text;
            if ($min_tls !== 'unknown') {
                $label_parts[] = sprintf('(TLS %s)', $min_tls);
            }
        } elseif ($curl_info && !empty($curl_info['ssl_version'])) {
            $label_parts[] = $curl_info['ssl_version'];
        } elseif ($curl_info && ($curl_info['features'] & CURL_VERSION_SSL)) {
            $label_parts[] = $this->language->get('text_ssl_tls_available_version_unknown');
        } else {
            $label_parts[] = $this->language->get('text_no_ssl_tls_detected');
        }

        return [
            'label' => implode(' ', $label_parts),
            'min_tls' => $min_tls,
        ];
    }

    /**
     * Gets the system requirements information.
     *
     * @return array
     */
    private function getRequirementsInfo(): array
    {
        $targets = [
            'php' => [
                'required' => '7.4',
                'recommended' => '8.2',
            ],
            'oc' => [
                'required' => '2.0',
                'recommended' => '4.0',
            ],
            'tls' => [
                'required' => '1.2',
                'recommended' => '1.3',
            ],
        ];

        $current = [
            'php' => PHP_VERSION,
            'oc' => VERSION,
            'tls' => $this->detectTlsCapability(),
        ];

        $ver_status = function ($cur, $req, $rec) {
            if ($cur === null) {
                return ['class' => 'morpos-danger', 'hint' => $this->language->get('text_not_detected')];
            }

            if (version_compare($cur, $req, '<')) {
                return ['class' => 'morpos-danger', 'hint' => $this->language->get('text_below_required')];
            }

            if (version_compare($cur, $rec, '<')) {
                return ['class' => 'morpos-warning', 'hint' => $this->language->get('text_allowed_but_discouraged')];
            }

            return ['class' => 'morpos-ok', 'hint' => $this->language->get('text_meets_recommended')];
        };

        $tls_status = function ($current, $required, $recommended) {
            if (!$current || $current['min_tls'] === 'unknown') {
                return [
                    'class' => 'morpos-danger',
                    'hint' => $this->language->get('text_unable_to_verify_tls_support')
                ];
            }

            if (version_compare($current['min_tls'], $required, '<')) {
                return ['class' => 'morpos-danger', 'hint' => $this->language->get('text_below_required')];
            }

            if (version_compare($current['min_tls'], $recommended, '<')) {
                return ['class' => 'morpos-warning', 'hint' => $this->language->get('text_allowed_but_discouraged')];
            }

            return ['class' => 'morpos-ok', 'hint' => $this->language->get('text_meets_recommended')];
        };

        return [
            [
                'label' => $this->language->get('text_php'),
                'cur' => $current['php'],
                'req' => $targets['php']['required'] . '+',
                'rec' => $targets['php']['recommended'] . '+',
                'status' => $ver_status($current['php'], $targets['php']['required'], $targets['php']['recommended']),
            ],
            [
                'label' => $this->language->get('text_opencart'),
                'cur' => $current['oc'],
                'req' => $targets['oc']['required'] . '+',
                'rec' => $targets['oc']['recommended'] . '+',
                'status' => $ver_status($current['oc'], $targets['oc']['required'], $targets['oc']['recommended']),
            ],
            [
                'label' => $this->language->get('text_tls'),
                'cur' => $current['tls'] ? $current['tls']['label'] : $this->language->get('text_unknown'),
                'req' => 'TLS ' . $targets['tls']['required'] . '+',
                'rec' => 'TLS ' . $targets['tls']['recommended'] . '+',
                'status' => $tls_status($current['tls'], $targets['tls']['required'], $targets['tls']['recommended']),
            ],
        ];
    }
}
