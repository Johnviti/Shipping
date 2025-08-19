<?php
/**
 * Admin Settings Page for Stackable Product Shipping
 */
class SPS_Admin_Settings {
    /**
     * Render the settings page
     */
    public static function render_page() {
        // Save settings if form was submitted
        if (isset($_POST['sps_save_settings']) && isset($_POST['sps_settings_nonce']) && 
            wp_verify_nonce($_POST['sps_settings_nonce'], 'save_sps_settings')) {
            
            self::save_settings();
        }
        
        // Get current settings
        $api_token = get_option('sps_api_token', '');
        $frenet_token = get_option('sps_frenet_token', '');
        $cargo_types = get_option('sps_cargo_types', '28');
        $test_origin_cep = get_option('sps_test_origin_cep', '01001000');
        $test_destination_cep = get_option('sps_test_destination_cep', '04538132');
        $enable_in_cart = get_option('sps_enable_in_cart', 'yes');
        $enable_in_checkout = get_option('sps_enable_in_checkout', 'yes');
        
        // Configurações de dimensões personalizadas



        
        ?>
        <div class="wrap">
            <h1>Configurações de Empilhamento</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_sps_settings', 'sps_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sps_api_token">Token da API Central do Frete</label></th>
                        <td>
                            <input type="text" name="sps_api_token" id="sps_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text">
                            <p class="description">Token de acesso à API Central do Frete.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sps_frenet_token">Token da API Frenet</label></th>
                        <td>
                            <input type="text" name="sps_frenet_token" id="sps_frenet_token" value="<?php echo esc_attr($frenet_token); ?>" class="regular-text">
                            <p class="description">Token de acesso à API Frenet. Se preenchido, será usado em conjunto com a API Central do Frete para obter mais cotações.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sps_cargo_types">Tipos de Carga</label></th>
                        <td>
                            <input type="text" name="sps_cargo_types" id="sps_cargo_types" value="<?php echo esc_attr($cargo_types); ?>" class="regular-text">
                            <p class="description">IDs dos tipos de carga separados por vírgula (ex: 28,29).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sps_test_origin_cep">CEP de Origem para Testes</label></th>
                        <td>
                            <input type="text" name="sps_test_origin_cep" id="sps_test_origin_cep" value="<?php echo esc_attr($test_origin_cep); ?>" class="regular-text">
                            <p class="description">CEP de origem usado para simulações de frete.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sps_test_destination_cep">CEP de Destino para Testes</label></th>
                        <td>
                            <input type="text" name="sps_test_destination_cep" id="sps_test_destination_cep" value="<?php echo esc_attr($test_destination_cep); ?>" class="regular-text">
                            <p class="description">CEP de destino usado para simulações de frete.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sps_enable_in_cart">Habilitar no Carrinho</label></th>
                        <td>
                            <select name="sps_enable_in_cart" id="sps_enable_in_cart">
                                <option value="yes" <?php selected($enable_in_cart, 'yes'); ?>>Sim</option>
                                <option value="no" <?php selected($enable_in_cart, 'no'); ?>>Não</option>
                            </select>
                            <p class="description">Habilitar empilhamento de produtos no cálculo de frete do carrinho.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sps_enable_in_checkout">Habilitar no Checkout</label></th>
                        <td>
                            <select name="sps_enable_in_checkout" id="sps_enable_in_checkout">
                                <option value="yes" <?php selected($enable_in_checkout, 'yes'); ?>>Sim</option>
                                <option value="no" <?php selected($enable_in_checkout, 'no'); ?>>Não</option>
                            </select>
                            <p class="description">Habilitar empilhamento de produtos no cálculo de frete do checkout.</p>
                        </td>
                    </tr>
                </table>
                

                
                <?php submit_button('Salvar Configurações', 'primary', 'sps_save_settings'); ?>
            </form>
            
            <div class="sps-api-test">
                <h2>Testar Conexão com as APIs</h2>
                <p>Clique nos botões abaixo para testar a conexão com as APIs de frete.</p>
                <button type="button" id="sps-test-api" class="button">Testar API Central do Frete</button>
                <button type="button" id="sps-test-frenet-api" class="button" style="margin-left: 10px;">Testar API Frenet</button>
                <div id="sps-api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // API test button handler for Central do Frete
            $('#sps-test-api').on('click', function() {
                var $button = $(this);
                var $result = $('#sps-api-test-result');
                
                $button.prop('disabled', true).text('Testando...');
                $result.html('<div class="notice notice-info"><p>Testando conexão com a API Central do Frete...</p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sps_test_api',
                        nonce: '<?php echo wp_create_nonce('sps_test_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var message = '';
                            if (response.data && typeof response.data === 'object' && response.data.message) {
                                message = response.data.message;
                            } else if (typeof response.data === 'string') {
                                message = response.data;
                            } else {
                                message = 'Conexão com a API realizada com sucesso!';
                            }
                            $result.html('<div class="notice notice-success"><p>' + message + '</p></div>');
                        } else {
                            var errorMsg = 'Erro na conexão com a API';
                            if (response.data && typeof response.data === 'object' && response.data.message) {
                                errorMsg = response.data.message;
                            } else if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            }
                            $result.html('<div class="notice notice-error"><p>Erro: ' + errorMsg + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error"><p>Erro ao conectar com o servidor.</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Testar API Central do Frete');
                    }
                });
            });
            
            // API test button handler for Frenet
            $('#sps-test-frenet-api').on('click', function() {
                var $button = $(this);
                var $result = $('#sps-api-test-result');
                
                $button.prop('disabled', true).text('Testando...');
                $result.html('<div class="notice notice-info"><p>Testando conexão com a API Frenet...</p></div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sps_test_frenet_api',
                        nonce: '<?php echo wp_create_nonce('sps_test_frenet_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var message = '';
                            if (response.data && typeof response.data === 'object' && response.data.message) {
                                message = response.data.message;
                            } else if (typeof response.data === 'string') {
                                message = response.data;
                            } else {
                                message = 'Conexão com a API Frenet realizada com sucesso!';
                            }
                            $result.html('<div class="notice notice-success"><p>' + message + '</p></div>');
                        } else {
                            var errorMsg = 'Erro na conexão com a API Frenet';
                            if (response.data && typeof response.data === 'object' && response.data.message) {
                                errorMsg = response.data.message;
                            } else if (typeof response.data === 'string') {
                                errorMsg = response.data;
                            }
                            $result.html('<div class="notice notice-error"><p>Erro: ' + errorMsg + '</p></div>');
                        }
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error"><p>Erro ao conectar com o servidor.</p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Testar API Frenet');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        // Sanitize and save settings
        $api_token = sanitize_text_field($_POST['sps_api_token']);
        $frenet_token = sanitize_text_field($_POST['sps_frenet_token']);
        $cargo_types = sanitize_text_field($_POST['sps_cargo_types']);
        $test_origin_cep = sanitize_text_field($_POST['sps_test_origin_cep']);
        $test_destination_cep = sanitize_text_field($_POST['sps_test_destination_cep']);
        $enable_in_cart = sanitize_text_field($_POST['sps_enable_in_cart']);
        $enable_in_checkout = sanitize_text_field($_POST['sps_enable_in_checkout']);
        
        // Configurações de dimensões personalizadas

        
        
        
        update_option('sps_api_token', $api_token);
        update_option('sps_frenet_token', $frenet_token);
        update_option('sps_cargo_types', $cargo_types);
        update_option('sps_test_origin_cep', $test_origin_cep);
        update_option('sps_test_destination_cep', $test_destination_cep);
        update_option('sps_enable_in_cart', $enable_in_cart);
        update_option('sps_enable_in_checkout', $enable_in_checkout);
        
        // Salvar configurações de dimensões personalizadas

        
        
        
        echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas com sucesso!</p></div>';
    }
}