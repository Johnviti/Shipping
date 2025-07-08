<?php
/**
 * Admin Simulator Page for Stackable Product Shipping
 */
class SPS_Admin_Simulator {
    /**
     * Render the simulator page
     */
    public static function render_page() {
        // Get API token (anonymized for display)
        $token = get_option('sps_api_token', '');
        $anonymized_token = '';
        if (!empty($token)) {
            $anonymized_token = substr($token, 0, 4) . '...' . substr($token, -4);
        }
        
        // Get test CEPs from settings
        $test_origin_cep = get_option('sps_test_origin_cep', '');
        $test_destination_cep = get_option('sps_test_destination_cep', '');
        
        // Get cargo types
        $cargo_types = get_option('sps_cargo_types', '28');
        ?>
        <div class="wrap">
            <h1>Simulador de Frete</h1>
            
            <div class="sps-api-info notice notice-info" style="margin-bottom: 15px; padding: 10px;">
                <p><strong>Informações da API:</strong> 
                    <?php if (!empty($anonymized_token)): ?>
                        Token: <?php echo esc_html($anonymized_token); ?> 
                    <?php else: ?>
                        Token não encontrado
                    <?php endif; ?>
                    | Tipo de carga: <?php echo esc_html($cargo_types); ?> 
                    | <a href="<?php echo admin_url('admin.php?page=sps-settings'); ?>">Configurar</a>
                </p>
            </div>
            
            <div class="sps-simulation-form">
                <div class="sps-form-row">
                    <label for="sps-simulation-origin">CEP de Origem:</label>
                    <input type="text" id="sps-simulation-origin" class="regular-text" placeholder="00000-000" value="<?php echo esc_attr($test_origin_cep); ?>">
                </div>
                <div class="sps-form-row">
                    <label for="sps-simulation-destination">CEP de Destino:</label>
                    <input type="text" id="sps-simulation-destination" class="regular-text" placeholder="00000-000" value="<?php echo esc_attr($test_destination_cep); ?>">
                </div>
                <div class="sps-form-row">
                    <label for="sps-simulation-value">Valor da Mercadoria (R$):</label>
                    <input type="number" id="sps-simulation-value" class="regular-text" step="0.01" min="0" value="100">
                </div>
                
                <div class="sps-simulation-tabs sps-input-tabs">
                    <button class="sps-tab-button active" data-input-tab="stacked">Pacote Empilhado</button>
                    <button class="sps-tab-button" data-input-tab="individual">Produtos Individuais</button>
                </div>
                
                <div id="sps-input-stacked" class="sps-input-content" style="display:block;">
                    <h4>Dados do Pacote Empilhado</h4>
                    <div class="sps-form-row">
                        <label for="sps-stacked-quantity">Quantidade:</label>
                        <input type="number" id="sps-stacked-quantity" value="3" min="1">
                    </div>
                    <div class="sps-form-row">
                        <label for="sps-stacked-width">Largura (cm):</label>
                        <input type="number" id="sps-stacked-width" value="20" min="1" step="0.01">
                    </div>
                    <div class="sps-form-row">
                        <label for="sps-stacked-height">Altura (cm):</label>
                        <input type="number" id="sps-stacked-height" value="20" min="1" step="0.01">
                    </div>
                    <div class="sps-form-row">
                        <label for="sps-stacked-length">Comprimento (cm):</label>
                        <input type="number" id="sps-stacked-length" value="20" min="1" step="0.01">
                    </div>
                    <div class="sps-form-row">
                        <label for="sps-stacked-weight">Peso (kg):</label>
                        <input type="number" id="sps-stacked-weight" value="1" min="0.01" step="0.01">
                    </div>
                </div>
                
                <div id="sps-input-individual" class="sps-input-content" style="display:none;">
                    <h4>Produtos Individuais</h4>
                    <table id="sps-individual-products-table" class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Quantidade</th>
                                <th>Largura (cm)</th>
                                <th>Altura (cm)</th>
                                <th>Comprimento (cm)</th>
                                <th>Peso (kg)</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="sps-individual-product-row">
                                <td><input type="number" class="sps-ind-quantity" value="4" min="1"></td>
                                <td><input type="number" class="sps-ind-width" value="10" min="1" step="0.01"></td>
                                <td><input type="number" class="sps-ind-height" value="10" min="1" step="0.01"></td>
                                <td><input type="number" class="sps-ind-length" value="10" min="1" step="0.01"></td>
                                <td><input type="number" class="sps-ind-weight" value="0.5" min="0.01" step="0.01"></td>
                                <td><button type="button" class="button sps-remove-ind-product">Remover</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" id="sps-add-ind-product" class="button">+ Adicionar Produto</button>
                </div>
                
                <div class="sps-form-row" style="margin-top: 20px;">
                    <button type="button" id="sps-run-simulation" class="button button-primary">Simular</button>
                </div>
            </div>
            
            <div id="sps-simulation-results" style="display:none;">
                <div class="sps-simulation-loading" style="display:none;">
                    <p>Consultando API, aguarde...</p>
                </div>
                
                <div class="sps-simulation-error" style="display:none;">
                    <p class="sps-error-message"></p>
                </div>
                
                <div class="sps-simulation-success" style="display:none;">
                    <h3>Resultados da Simulação</h3>
                    
                    <div class="sps-simulation-tabs">
                        <button class="sps-tab-button active" data-tab="separate">Produtos Separados</button>
                        <button class="sps-tab-button" data-tab="combined">Pacote Único</button>
                        <button class="sps-tab-button" data-tab="comparison">Comparação</button>
                    </div>
                    
                    <div id="sps-tab-separate" class="sps-tab-content" style="display:block;">
                        <h4>Cotação para Produtos Separados</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                        <th>Fonte</th>
                                        <th>Transportadora</th>
                                        <th>Tipo de Serviço</th>
                                        <th>Preço (R$)</th>
                                        <th>Prazo (dias)</th>
                                    </tr>
                            </thead>
                            <tbody id="sps-separate-results">
                                <!-- Resultados serão inseridos aqui via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="sps-tab-combined" class="sps-tab-content" style="display:none;">
                        <h4>Cotação para Pacote Único</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                               <tr>
                                        <th>Fonte</th>
                                        <th>Transportadora</th>
                                        <th>Tipo de Serviço</th>
                                        <th>Preço (R$)</th>
                                        <th>Prazo (dias)</th>
                                    </tr>
                            </thead>
                            <tbody id="sps-combined-results">
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="sps-tab-comparison" class="sps-tab-content" style="display:none;">
                        <h4>Comparação de Valores</h4>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Transportadora</th>
                                    <th>Produtos Separados (R$)</th>
                                    <th>Pacote Único (R$)</th>
                                    <th>Economia (R$)</th>
                                    <th>Economia (%)</th>
                                </tr>
                            </thead>
                            <tbody id="sps-comparison-results">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}