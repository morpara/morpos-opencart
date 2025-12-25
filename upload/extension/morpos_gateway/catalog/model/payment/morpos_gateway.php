<?php

namespace Opencart\Catalog\Model\Extension\MorposGateway\Payment;

/**
 * Class MorposGateway
 *
 * @package
 */
class MorposGateway extends \Opencart\System\Engine\Model
{
    /**
     * @param array $address
     *
     * @return array
     */
    public function getMethods(array $address = []): array
    {
        $this->load->language('extension/morpos_gateway/payment/morpos_gateway');

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_morpos_gateway_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int) $this->config->get('payment_morpos_gateway_geo_zone_id') . "' AND `country_id` = '" . (int) $address['country_id'] . "' AND (`zone_id` = '" . (int) $address['zone_id'] . "' OR `zone_id` = '0')");

            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];

        $name = $this->language->get('text_title');

        if ($status) {
            $option_data['morpos_gateway'] = [
                'code' => 'morpos_gateway.morpos_gateway',
                'name' => $name,
            ];

            $method_data = [
                'code' => 'morpos_gateway',
                'name' => $name,
                'option' => $option_data,
                'sort_order' => $this->config->get('payment_morpos_gateway_sort_order')
            ];
        }

        return $method_data;
    }
}
