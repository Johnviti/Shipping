<?php
/**
 * Plugin Name: Vipex
 * Description: Calcula frete personalizado para móveis com base em tabela VIPEX.
 * Version: 1.0
 * Author: John Amorim
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Função para registrar logs no WooCommerce
function vipex_log($message) {
    if (function_exists('wc_get_logger')) {
        $logger = wc_get_logger();
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        $logger->debug($message, array('source' => 'vipex-shipping'));
    }
}

add_action('woocommerce_shipping_init', 'frete_vipex_init');
add_filter('woocommerce_shipping_methods', 'add_frete_vipex_method');

function frete_vipex_init() {
    class WC_Shipping_Frete_VIPEX extends WC_Shipping_Method {

        public function __construct() {
            $this->id = 'frete_vipex';
            $this->method_title = 'Frete VIPEX';
            $this->method_description = 'Método de frete baseado em tabela VIPEX.';
            $this->enabled = "yes";
            $this->init();
        }

        function init() {
            $this->init_form_fields();
            $this->init_settings();
        }

        public function calculate_shipping($package = array()) {
            vipex_log('Iniciando cálculo de frete VIPEX');
            vipex_log('Pacote recebido: ' . print_r($package, true));
            
            $destino = $package['destination']['state']; // Ex: 'BA'
            $cidade_destino = strtoupper($package['destination']['city']); // Ex: 'SALVADOR'
            
            vipex_log("Destino: {$destino}, Cidade: {$cidade_destino}");

            $praca_destino = $this->determinar_praca_destino($destino, $cidade_destino);
            vipex_log("Praça de destino determinada: {$praca_destino}");
            
            $tabela_frete = $this->get_tabela_frete();

            if (!$praca_destino || !isset($tabela_frete[$praca_destino])) {
                vipex_log("Praça de destino não reconhecida ou não encontrada na tabela: {$praca_destino}");
                return; // Não aplica o método se a praça não for reconhecida
            }

            if (!isset($tabela_frete[$praca_destino])) return;

            $dados = $tabela_frete[$praca_destino];
            vipex_log("Dados da tabela para {$praca_destino}: " . print_r($dados, true));
            
            $valor_nf = $this->get_valor_produtos($package);
            $peso_kg = $this->get_peso_total($package);
            $cubagem = $this->get_cubagem_total($package); // deve converter m³ em peso (Fator 100kg/m³)
            
            vipex_log("Valor NF: {$valor_nf}, Peso: {$peso_kg}kg, Cubagem: {$cubagem}m³");

            $peso_cobrado = max($peso_kg, $cubagem * 100);
            vipex_log("Peso cobrado: {$peso_cobrado}kg");

            $frete_percentual = ($dados['percentual'] / 100) * $valor_nf;
            $frete_total = $frete_percentual + $dados['despacho'] + $dados['pedagio'];
            
            vipex_log("Frete percentual: {$frete_percentual}, Despacho: {$dados['despacho']}, Pedágio: {$dados['pedagio']}");

            // +30% para consumidor final?
            if ($this->is_consumidor_final()) {
                vipex_log("Aplicando adicional de 30% para consumidor final");
                $frete_total *= 1.3;
            }
            
            vipex_log("Valor final do frete: {$frete_total}");

            $rate = array(
                'id' => $this->id,
                'label' => "Frete via Vipex",
                'cost' => $frete_total,
                'calc_tax' => 'per_item'
            );
            
            vipex_log("Taxa de frete adicionada: " . print_r($rate, true));

            $this->add_rate($rate);
        }

        private function determinar_praca_destino($estado, $cidade) {
            $cidade = strtoupper($cidade);
            $estado = strtoupper($estado);
            
            vipex_log("Determinando praça para Estado: {$estado}, Cidade: {$cidade}");

            if ($estado === 'SP') {
                if (in_array($cidade, ['SAO PAULO', 'SP'])) return 'SP-CAP';
                if (strpos($cidade, 'LITORAL NORTE') !== false) return 'SP-LITNOR';
                if (strpos($cidade, 'LITORAL SUL') !== false) return 'SP-LITSUL';
                if (strpos($cidade, 'VALE DO PARAIBA') !== false) return 'SP-VPB';
                if (strpos($cidade, 'GRANDE SAO PAULO') !== false || strpos($cidade, 'GDSP') !== false) return 'SP-GDSP';
                if (strpos($cidade, 'INTERIOR 1') !== false) return 'SP-INT1';
                if (strpos($cidade, 'INTERIOR 2') !== false) return 'SP-INT2';
                if (strpos($cidade, 'INTERIOR 3') !== false) return 'SP-INT3';
                return 'SP-CAP';
            }

            if ($estado === 'BA') {
                if (strpos($cidade, 'RECÔNCAVO') !== false || strpos($cidade, 'RECONCAVO') !== false) return 'BA-RCV';
                if ($cidade === 'SALVADOR') return 'BA-CAP';
                return 'BA-NOR';
            }

            if ($estado === 'DF') {
                if (strpos($cidade, 'INTERIOR') !== false) return 'DF-INT1';
                return 'DF-CAP';
            }

            if ($estado === 'GO') {
                if (strpos($cidade, 'INTERIOR') !== false) return 'GO-INT1';
                return 'GO-CAP';
            }

            if ($estado === 'MG') {
                if (strpos($cidade, 'UBERLANDIA') !== false || strpos($cidade, 'UBE') !== false) return 'MG-UBE';
                if (strpos($cidade, 'INTERIOR') !== false) return 'MG-INT1';
                if (strpos($cidade, 'SUL') !== false) return 'MG-SUL';
                return 'MG-CAP';
            }

            if ($estado === 'PR') {
                if (strpos($cidade, 'INTERIOR 2') !== false) return 'PR-INT2';
                if (strpos($cidade, 'INTERIOR') !== false) return 'PR-INT';
                if (strpos($cidade, 'NORTE') !== false || strpos($cidade, 'NOR') !== false) {
                    if (strpos($cidade, '(2)') !== false) return 'PR-NOR (2)';
                    return 'PR-NOR (1)';
                }
                return 'PR-CAP';
            }

            if ($estado === 'RJ') {
                if ($cidade === 'RIO DE JANEIRO') return 'RJ-CAP';
                return 'RJ-INT';
            }

            if ($estado === 'RS') {
                if (strpos($cidade, 'INTERIOR 2') !== false) return 'RS-INT2';
                if (strpos($cidade, 'INTERIOR') !== false) return 'RS-INT1';
                if (strpos($cidade, 'SERRA 2') !== false) return 'RS-SERRA2';
                if (strpos($cidade, 'SERRA') !== false) return 'RS-SERRA';
                if (strpos($cidade, 'LITORAL') !== false) return 'RS-LIT';
                return 'RS-CAP';
            }

            if ($estado === 'SC') {
                if (strpos($cidade, 'OESTE') !== false) return 'SC-OESTE';
                if (strpos($cidade, 'SUL') !== false) return 'SC-SUL';
                if (strpos($cidade, 'LITORAL') !== false) return 'SC-LIT';
                if (strpos($cidade, 'NORTE') !== false) return 'SC-NOR';
                return 'SC-INT';
            }
            
            vipex_log("Nenhuma praça encontrada para Estado: {$estado}, Cidade: {$cidade}");
            return null;
        }

        private function get_tabela_frete() {
            return [
                'BA-CAP' => ['percentual' => 10.39, 'despacho' => 32.26, 'pedagio' => 16.13],
                'BA-NOR' => ['percentual' => 13.66, 'despacho' => 32.26, 'pedagio' => 16.13],
                'BA-RCV' => ['percentual' => 14.11, 'despacho' => 32.26, 'pedagio' => 16.13],
                'DF-CAP' => ['percentual' => 7.68, 'despacho' => 32.26, 'pedagio' => 16.13],
                'DF-INT1' => ['percentual' => 7.93, 'despacho' => 32.26, 'pedagio' => 16.13],
                'ES-CAP' => ['percentual' => 13.23, 'despacho' => 32.26, 'pedagio' => 16.13],
                'GO-CAP' => ['percentual' => 8.64, 'despacho' => 32.26, 'pedagio' => 16.13],
                'GO-INT1' => ['percentual' => 9.02, 'despacho' => 32.26, 'pedagio' => 16.13],
                'MG-CAP' => ['percentual' => 8.72, 'despacho' => 34.09, 'pedagio' => 17.05],
                'MG-INT1' => ['percentual' => 10.86, 'despacho' => 34.09, 'pedagio' => 17.05],
                'MG-SUL' => ['percentual' => 9.53, 'despacho' => 34.09, 'pedagio' => 17.05],
                'MG-UBE' => ['percentual' => 10.65, 'despacho' => 34.09, 'pedagio' => 17.05],
                'PR-CAP' => ['percentual' => 7.22, 'despacho' => 34.09, 'pedagio' => 17.05],
                'PR-INT' => ['percentual' => 7.79, 'despacho' => 34.09, 'pedagio' => 17.05],
                'PR-INT2' => ['percentual' => 10.86, 'despacho' => 34.09, 'pedagio' => 17.05],
                'PR-NOR (1)' => ['percentual' => 7.22, 'despacho' => 34.09, 'pedagio' => 17.05],
                'PR-NOR (2)' => ['percentual' => 7.22, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RJ-CAP' => ['percentual' => 7.00, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RJ-INT' => ['percentual' => 9.57, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RS-CAP' => ['percentual' => 8.85, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RS-INT1' => ['percentual' => 9.26, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RS-INT2' => ['percentual' => 12.26, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RS-LIT' => ['percentual' => 10.55, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RS-SERRA' => ['percentual' => 9.26, 'despacho' => 34.09, 'pedagio' => 17.05],
                'RS-SERRA2' => ['percentual' => 7.66, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SC-INT' => ['percentual' => 8.54, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SC-LIT' => ['percentual' => 9.15, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SC-NOR' => ['percentual' => 8.19, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SC-OESTE' => ['percentual' => 12.78, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SC-SUL' => ['percentual' => 10.11, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-CAP' => ['percentual' => 6.88, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-GDSP' => ['percentual' => 6.88, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-INT1' => ['percentual' => 7.00, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-INT2' => ['percentual' => 8.42, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-INT3' => ['percentual' => 10.76, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-LITNOR' => ['percentual' => 7.00, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-LITSUL' => ['percentual' => 7.53, 'despacho' => 34.09, 'pedagio' => 17.05],
                'SP-VPB' => ['percentual' => 6.88, 'despacho' => 34.09, 'pedagio' => 17.05],
            ];
        }        

        private function get_valor_produtos($package) {
            $total = 0;
            foreach ($package['contents'] as $item) {
                $total += $item['line_total'];
            }
            return $total;
        }

        private function get_peso_total($package) {
            $peso = 0;
            foreach ($package['contents'] as $item) {
                $peso += $item['data']->get_weight() * $item['quantity'];
            }
            return $peso;
        }

        private function get_cubagem_total($package) {
            $cubagem = 0;
            foreach ($package['contents'] as $item) {
                $largura = $item['data']->get_width() / 100; 
                $altura = $item['data']->get_height() / 100;
                $comprimento = $item['data']->get_length() / 100;
                $cubagem += $largura * $altura * $comprimento * $item['quantity'];
            }
            return $cubagem;
        }

        private function is_consumidor_final() {
            return true;
        }
    }
}

function add_frete_vipex_method($methods) {
    $methods['frete_vipex'] = 'WC_Shipping_Frete_VIPEX';
    return $methods;
}