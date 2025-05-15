<?php
defined('ABSPATH') || exit;

$logger = function_exists('wc_get_logger') ? wc_get_logger() : null;

if (!current_user_can('manage_woocommerce')) {
    wp_die(__('Você não tem permissões suficientes para acessar esta página.', 'woocommerce-stackable-shipping'));
}

if (isset($_POST['save_stackable_products'])) {
    if (isset($_POST['stackable_products_nonce']) && wp_verify_nonce($_POST['stackable_products_nonce'], 'save_stackable_products')) {
        $stackable_products_config = isset($_POST['stackable_products_config']) ? $_POST['stackable_products_config'] : array();
        
        update_option('wc_stackable_shipping_products', $stackable_products_config);
        
        if ($logger) $logger->info('Configurações de produtos empilháveis salvas', array('source' => 'stackable-shipping', 'data' => $stackable_products_config));
        
        echo '<div class="notice notice-success"><p>' . __('Configurações de produtos empilháveis salvas com sucesso!', 'woocommerce-stackable-shipping') . '</p></div>';
    }
}

if (isset($_POST['save_shipping_settings'])) {
    if (isset($_POST['shipping_settings_nonce']) && wp_verify_nonce($_POST['shipping_settings_nonce'], 'save_shipping_settings')) {
        $debug_enabled = isset($_POST['enable_debug']) ? 1 : 0;
        
        update_option('wc_stackable_shipping_debug_enabled', $debug_enabled);
        
        if ($logger) $logger->info('Configurações avançadas salvas', array('source' => 'stackable-shipping', 'debug_enabled' => $debug_enabled));
        
        echo '<div class="notice notice-success"><p>' . __('Configurações salvas com sucesso!', 'woocommerce-stackable-shipping') . '</p></div>';
    }
}
?>

<div class="wrap wc-stackable-shipping-admin">
    <h1><?php echo esc_html(__('Agrupamento de Produtos para Frete', 'woocommerce-stackable-shipping')); ?></h1>

    <div class="notice notice-info">
        <p><?php _e('Configure como os produtos podem ser agrupados para cálculo de frete. Os produtos marcados como empilháveis serão organizados em grupos para otimizar as dimensões durante o cálculo do frete.', 'woocommerce-stackable-shipping'); ?></p>
    </div>

    <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
        <a href="?page=wc-stackable-shipping&tab=products" class="nav-tab <?php echo empty($_GET['tab']) || $_GET['tab'] === 'products' ? 'nav-tab-active' : ''; ?>"><?php _e('Produtos Empilháveis', 'woocommerce-stackable-shipping'); ?></a>
        <a href="?page=wc-stackable-shipping&tab=settings" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] === 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Configurações', 'woocommerce-stackable-shipping'); ?></a>
    </nav>

    <?php
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'products';
    
    switch ($current_tab) {
        case 'settings':
            display_settings_tab();
            break;
        default:
            display_products_tab();
            break;
    }
    ?>
</div>

<?php
/**
 * Exibe a aba de produtos empilháveis
 */
function display_products_tab() {
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    );

    $products_query = new WP_Query($args);
    $products = array();
    
    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) { 
            $products_query->the_post();
            $product = wc_get_product(get_the_ID());
            $products[get_the_ID()] = array(
                'id' => get_the_ID(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'dimensions' => array(
                    'width' => $product->get_width(),
                    'length' => $product->get_length(),
                    'height' => $product->get_height()
                )
            );
        }
        wp_reset_postdata();
    }
    
    // Obter as configurações salvas dos produtos
    $saved_configs = get_option('wc_stackable_shipping_products', array());
    ?>
    
    <div class="card">
        <h2><?php _e('Configurar Produtos Empilháveis', 'woocommerce-stackable-shipping'); ?></h2>
        <p><?php _e('Selecione os produtos que podem ser empilhados e configure suas propriedades de empilhamento:', 'woocommerce-stackable-shipping'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('save_stackable_products', 'stackable_products_nonce'); ?>
            
            <table class="widefat stackable-products-table">
                <thead>
                    <tr>
                        <th width="40"><?php _e('Empilhável', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Produto', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('SKU', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Dimensões (LxCxA)', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Empilhamento Máximo', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Incremento de Altura (cm)', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Incremento de Comprimento (cm)', 'woocommerce-stackable-shipping');?></th>
                        <th><?php _e('Incremento de Largura (cm)', 'woocommerce-stackable-shipping');?></th>
                        <th><?php _e('Incremento de Peso (kg)', 'woocommerce-stackable-shipping');?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product_id => $product_data): ?>
                        <?php
                        $is_stackable = isset($saved_configs[$product_id]['is_stackable']) ? $saved_configs[$product_id]['is_stackable'] : false;
                        $max_stack = isset($saved_configs[$product_id]['max_stack']) ? $saved_configs[$product_id]['max_stack'] : 1;
                        $height_increment = isset($saved_configs[$product_id]['height_increment']) ? $saved_configs[$product_id]['height_increment'] : 0;
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       name="stackable_products_config[<?php echo $product_id; ?>][is_stackable]" 
                                       value="1" 
                                       <?php checked($is_stackable, true); ?> />
                            </td>
                            <td><?php echo esc_html($product_data['name']); ?></td>
                            <td><?php echo esc_html($product_data['sku']); ?></td>
                            <td>
                                <?php 
                                echo esc_html($product_data['dimensions']['width'] . ' × ' . 
                                             $product_data['dimensions']['length'] . ' × ' . 
                                             $product_data['dimensions']['height'] . ' ' . 
                                             get_option('woocommerce_dimension_unit')); 
                                ?>
                            </td>
                            <td>
                                <input type="number" 
                                       name="stackable_products_config[<?php echo $product_id; ?>][max_stack]" 
                                       value="<?php echo esc_attr($max_stack); ?>" 
                                       min="1" 
                                       step="1" 
                                       class="small-text" />
                            </td>
                            <td>
                                <input type="number" 
                                       name="stackable_products_config[<?php echo $product_id; ?>][height_increment]" 
                                       value="<?php echo esc_attr($height_increment); ?>" 
                                       min="0" 
                                       step="0.1" 
                                       class="small-text" />
                            </td>
                            <td>
                                <input type="number" 
                                       name="stackable_products_config[<?php echo $product_id; ?>][length_increment]" 
                                       value="<?php echo esc_attr(isset($saved_configs[$product_id]['length_increment']) ? $saved_configs[$product_id]['length_increment'] : 0); ?>" 
                                       min="0" 
                                       step="0.1" 
                                       class="small-text" />
                            </td>
                            <td>
                                <input type="number" 
                                       name="stackable_products_config[<?php echo $product_id; ?>][width_increment]" 
                                       value="<?php echo esc_attr(isset($saved_configs[$product_id]['width_increment']) ? $saved_configs[$product_id]['width_increment'] : 0); ?>" 
                                       min="0" 
                                       step="0.1" 
                                       class="small-text" />
                            </td>
                            <td>
                                <input type="number" 
                                       name="stackable_products_config[<?php echo $product_id; ?>][weight_increment]" 
                                       value="<?php echo esc_attr(isset($saved_configs[$product_id]['weight_increment']) ? $saved_configs[$product_id]['weight_increment'] : 0); ?>" 
                                       min="0" 
                                       step="0.01" 
                                       class="small-text" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_stackable_products" class="button-primary" value="<?php _e('Salvar Configurações', 'woocommerce-stackable-shipping'); ?>" />
            </p>
        </form>
    </div>
    <?php
}

/**
 * Exibe a aba de configurações
 */
function display_settings_tab() {
    $debug_enabled = get_option('wc_stackable_shipping_debug_enabled', 0);
    ?>
    
    <div class="card">
        <h2><?php _e('Configurações Avançadas', 'woocommerce-stackable-shipping'); ?></h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('save_shipping_settings', 'shipping_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Depuração', 'woocommerce-stackable-shipping'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_debug" value="1" <?php checked($debug_enabled, 1); ?> />
                            <?php _e('Habilitar informações de depuração (apenas para administradores)', 'woocommerce-stackable-shipping'); ?>
                        </label>
                        <p class="description"><?php _e('Exibe informações detalhadas sobre o agrupamento de produtos no carrinho e checkout.', 'woocommerce-stackable-shipping'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_shipping_settings" class="button-primary" value="<?php _e('Salvar Configurações', 'woocommerce-stackable-shipping'); ?>" />
            </p>
        </form>
    </div>
    <?php
}
?>