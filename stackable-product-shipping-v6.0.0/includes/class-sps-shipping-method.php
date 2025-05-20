<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Shipping_SPS_Group')) {
    class WC_Shipping_SPS_Group extends WC_Shipping_Method {

        public function __construct() {
            $this->id                 = 'sps_group_shipping';
            $this->method_title       = 'Frete Empilhado (Grupo)';
            $this->method_description = 'Método de entrega baseado em empilhamento de grupos definidos.';
            $this->enabled            = 'yes';
            $this->title              = 'Frete Empilhado';
            $this->supports           = ['shipping-zones', 'instance-settings'];
            
            $this->init();
        }

        public function init() {
            $this->init_form_fields();
            $this->init_settings();
            $this->enabled = $this->get_option('enabled');
            $this->title   = $this->get_option('title');
            
            add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title'   => 'Ativar',
                    'type'    => 'checkbox',
                    'label'   => 'Ativar método de frete empilhado',
                    'default' => 'yes',
                ],
                'title' => [
                    'title'       => 'Título',
                    'type'        => 'text',
                    'description' => 'Título exibido no checkout',
                    'default'     => 'Frete Empilhado',
                ],
            ];
        }

        public function calculate_shipping($package = []) {
            if (!isset($package['sps_group'])) {
                return;
            }

            $group = $package['sps_group'];

            $peso = floatval($group['weight']);
            $altura = floatval($group['height']) > 0 ? floatval($group['height']) : floatval($group['stacking_ratio']);
            $largura = floatval($group['width']) > 0 ? floatval($group['width']) : floatval($group['stacking_ratio']);
            $comprimento = floatval($group['length']) > 0 ? floatval($group['length']) : floatval($group['stacking_ratio']);

            // Simulação de custo com base em dimensões e peso (exemplo básico)
            $volume = ($altura * $largura * $comprimento) / 6000; // fator cúbico
            $peso_cobravel = max($peso, $volume);
            $custo = 20 + ($peso_cobravel * 3.5); // base + R$/kg

            $rate = [
                'id'    => $this->id,
                'label' => $this->title,
                'cost'  => $custo,
                'package' => $package,
            ];

            $this->add_rate($rate);
        }
    }
}
