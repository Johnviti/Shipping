<?php
defined('ABSPATH') || exit;

// Verifica permissões de acesso
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('Você não tem permissões suficientes para acessar esta página.', 'woocommerce-stackable-shipping'));
}

// Processar formulário de configuração de produtos
if (isset($_POST['save_stackable_products'])) {
    if (isset($_POST['stackable_products_nonce']) && wp_verify_nonce($_POST['stackable_products_nonce'], 'save_stackable_products')) {
        $stackable_products_config = isset($_POST['stackable_products_config']) ? $_POST['stackable_products_config'] : array();
        
        update_option('wc_stackable_shipping_products', $stackable_products_config);
        
        echo '<div class="notice notice-success"><p>' . __('Configurações de produtos empilháveis salvas com sucesso!', 'woocommerce-stackable-shipping') . '</p></div>';
    }
}

// Processar formulário de relações de empilhamento
if (isset($_POST['save_stacking_relationships'])) {
    if (isset($_POST['stackable_relationships_nonce']) && wp_verify_nonce($_POST['stackable_relationships_nonce'], 'save_stackable_relationships')) {
        $stacking_groups = isset($_POST['stacking_groups']) ? $_POST['stacking_groups'] : array();
        
        update_option('wc_stackable_shipping_relationships', $stacking_groups);
        
        echo '<div class="notice notice-success"><p>' . __('Relações de empilhamento salvas com sucesso!', 'woocommerce-stackable-shipping') . '</p></div>';
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
        <a href="?page=wc-stackable-shipping&tab=relationships" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] === 'relationships' ? 'nav-tab-active' : ''; ?>"><?php _e('Relações de Empilhamento', 'woocommerce-stackable-shipping'); ?></a>
        <a href="?page=wc-stackable-shipping&tab=examples" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] === 'examples' ? 'nav-tab-active' : ''; ?>"><?php _e('Exemplos', 'woocommerce-stackable-shipping'); ?></a>
    </nav>

    <?php
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'products';
    
    switch ($current_tab) {
        case 'relationships':
            display_relationships_tab();
            break;
        case 'examples':
            display_examples_tab();
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
    // Buscar todos os produtos
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($products)) : ?>
                        <?php foreach ($products as $product_id => $product) : 
                            $is_stackable = isset($saved_configs[$product_id]['is_stackable']) ? $saved_configs[$product_id]['is_stackable'] : false;
                            $max_stack = isset($saved_configs[$product_id]['max_stack']) ? $saved_configs[$product_id]['max_stack'] : 3;
                            $height_increment = isset($saved_configs[$product_id]['height_increment']) ? $saved_configs[$product_id]['height_increment'] : '';
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" 
                                           name="stackable_products_config[<?php echo esc_attr($product_id); ?>][is_stackable]" 
                                           value="1" 
                                           <?php checked($is_stackable, true); ?> 
                                           class="enable-stackable">
                                </td>
                                <td>
                                    <?php echo esc_html($product['name']); ?>
                                    <input type="hidden" name="stackable_products_config[<?php echo esc_attr($product_id); ?>][name]" value="<?php echo esc_attr($product['name']); ?>">
                                </td>
                                <td><?php echo esc_html($product['sku']); ?></td>
                                <td>
                                    <?php 
                                    echo esc_html($product['dimensions']['width']) . ' × ' . 
                                         esc_html($product['dimensions']['length']) . ' × ' . 
                                         esc_html($product['dimensions']['height']) . ' ' . 
                                         esc_html(get_option('woocommerce_dimension_unit')); 
                                    ?>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="stackable_products_config[<?php echo esc_attr($product_id); ?>][max_stack]" 
                                           value="<?php echo esc_attr($max_stack); ?>" 
                                           min="1" 
                                           step="1" 
                                           class="small-text stackable-field" 
                                           <?php echo !$is_stackable ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="stackable_products_config[<?php echo esc_attr($product_id); ?>][height_increment]" 
                                           value="<?php echo esc_attr($height_increment); ?>" 
                                           min="0" 
                                           step="0.01" 
                                           placeholder="<?php echo esc_attr($product['dimensions']['height']); ?>"
                                           class="small-text stackable-field" 
                                           <?php echo !$is_stackable ? 'disabled' : ''; ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="6"><?php _e('Nenhum produto encontrado.', 'woocommerce-stackable-shipping'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select id="bulk-action-selector-bottom">
                        <option value="-1"><?php _e('Ações em massa', 'woocommerce-stackable-shipping'); ?></option>
                        <option value="enable"><?php _e('Marcar como empilháveis', 'woocommerce-stackable-shipping'); ?></option>
                        <option value="disable"><?php _e('Desmarcar como empilháveis', 'woocommerce-stackable-shipping'); ?></option>
                    </select>
                    <button type="button" class="button" id="doaction"><?php _e('Aplicar', 'woocommerce-stackable-shipping'); ?></button>
                </div>
                <div class="alignright">
                    <input type="submit" name="save_stackable_products" class="button button-primary" value="<?php esc_attr_e('Salvar Configurações', 'woocommerce-stackable-shipping'); ?>">
                </div>
                <br class="clear">
            </div>
        </form>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Habilitar/Desabilitar campos quando a checkbox é alterada
            $('.enable-stackable').on('change', function() {
                var row = $(this).closest('tr');
                if ($(this).is(':checked')) {
                    row.find('.stackable-field').prop('disabled', false);
                } else {
                    row.find('.stackable-field').prop('disabled', true);
                }
            });
            
            // Ações em massa
            $('#doaction').on('click', function() {
                var action = $('#bulk-action-selector-bottom').val();
                
                if (action === 'enable') {
                    $('.enable-stackable').prop('checked', true).trigger('change');
                } else if (action === 'disable') {
                    $('.enable-stackable').prop('checked', false).trigger('change');
                }
            });
        });
        </script>
    </div>
    
    <div class="card">
        <h2><?php _e('Produtos Configurados como Empilháveis', 'woocommerce-stackable-shipping'); ?></h2>
        
        <?php
        // Obter produtos configurados como empilháveis
        $stackable_products = array();
        foreach ($saved_configs as $product_id => $config) {
            if (!empty($config['is_stackable'])) {
                if (isset($products[$product_id])) {
                    $stackable_products[$product_id] = array_merge($products[$product_id], array(
                        'max_stack' => $config['max_stack'],
                        'height_increment' => $config['height_increment']
                    ));
                }
            }
        }
        ?>
        
        <?php if (!empty($stackable_products)) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Produto', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('SKU', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Dimensões Originais (LxCxA)', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Empilhamento Máximo', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Incremento de Altura', 'woocommerce-stackable-shipping'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stackable_products as $product_id => $product) : ?>
                        <tr>
                            <td><?php echo esc_html($product['name']); ?></td>
                            <td><?php echo esc_html($product['sku']); ?></td>
                            <td>
                                <?php 
                                echo esc_html($product['dimensions']['width']) . ' × ' . 
                                     esc_html($product['dimensions']['length']) . ' × ' . 
                                     esc_html($product['dimensions']['height']) . ' ' . 
                                     esc_html(get_option('woocommerce_dimension_unit')); 
                                ?>
                            </td>
                            <td><?php echo esc_html($product['max_stack']); ?></td>
                            <td>
                                <?php 
                                echo esc_html($product['height_increment']) . ' ' . 
                                     esc_html(get_option('woocommerce_dimension_unit')); 
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('Nenhum produto foi configurado como empilhável ainda.', 'woocommerce-stackable-shipping'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Exibe a aba de relações de empilhamento
 */
function display_relationships_tab() {
    $saved_configs = get_option('wc_stackable_shipping_products', array());
    
    // Filtrar apenas os produtos empilháveis
    $products = array();
    foreach ($saved_configs as $product_id => $config) {
        if (!empty($config['is_stackable'])) {
            $product = wc_get_product($product_id);
            if ($product) {
                $products[$product_id] = array(
                    'id' => $product_id,
                    'name' => $config['name'] ?? $product->get_name(),
                    'sku' => $product->get_sku(),
                    'dimensions' => $product->get_width() . '×' . $product->get_length() . '×' . $product->get_height() . ' ' . get_option('woocommerce_dimension_unit')
                );
            }
        }
    }
    
    // Recuperar grupos de empilhamento existentes
    $existing_groups = get_option('wc_stackable_shipping_relationships', array());
    ?>
    
    <div class="card">
        <h2><?php _e('Relações de Empilhamento entre Produtos', 'woocommerce-stackable-shipping'); ?></h2>
        <p><?php _e('Defina quais produtos podem ser empilhados juntos. Os produtos em um mesmo grupo serão considerados compatíveis para empilhamento.', 'woocommerce-stackable-shipping'); ?></p>
        
        <?php if (empty($products)) : ?>
            <div class="notice notice-warning">
                <p><?php _e('Nenhum produto está marcado como empilhável. Primeiro, configure produtos como empilháveis na aba "Produtos Empilháveis".', 'woocommerce-stackable-shipping'); ?></p>
            </div>
        <?php else : ?>
            <form method="post" action="">
                <?php wp_nonce_field('save_stackable_relationships', 'stackable_relationships_nonce'); ?>
                
                <div id="stacking-groups-container">
                    <?php 
                    if (!empty($existing_groups)) {
                        foreach ($existing_groups as $group_id => $group) {
                            display_stacking_group($group_id, $group, $products);
                        }
                    } else {
                        display_stacking_group(1, array(), $products);
                    }
                    ?>
                </div>
                
                <div class="stacking-controls">
                    <button type="button" id="add-stacking-group" class="button"><?php _e('Adicionar Grupo de Empilhamento', 'woocommerce-stackable-shipping'); ?></button>
                    <input type="submit" name="save_stacking_relationships" class="button button-primary" value="<?php esc_attr_e('Salvar Relações de Empilhamento', 'woocommerce-stackable-shipping'); ?>">
                </div>
            </form>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                var groupCount = <?php echo !empty($existing_groups) ? max(array_keys($existing_groups)) : 1; ?>;
                
                $('#add-stacking-group').on('click', function() {
                    groupCount++;
                    var template = `
                        <div class="stacking-group" id="stacking-group-${groupCount}">
                            <h3><?php _e('Grupo de Empilhamento', 'woocommerce-stackable-shipping'); ?> #${groupCount}</h3>
                            <div class="group-settings">
                                <label>
                                    <?php _e('Nome do Grupo:', 'woocommerce-stackable-shipping'); ?>
                                    <input type="text" name="stacking_groups[${groupCount}][name]" placeholder="<?php esc_attr_e('Ex: Roupas pequenas', 'woocommerce-stackable-shipping'); ?>">
                                </label>
                                <label>
                                    <?php _e('Máximo de itens no grupo:', 'woocommerce-stackable-shipping'); ?>
                                    <input type="number" name="stacking_groups[${groupCount}][max_items]" value="5" min="1" step="1">
                                </label>
                            </div>
                            <div class="group-products">
                                <h4><?php _e('Produtos neste grupo:', 'woocommerce-stackable-shipping'); ?></h4>
                                <select name="stacking_groups[${groupCount}][products][]" multiple="multiple" class="product-select" style="width: 100%; min-height: 150px;">
                                    <?php foreach ($products as $product_id => $product) : ?>
                                    <option value="<?php echo esc_attr($product_id); ?>"><?php echo esc_html($product['name'] . ' (' . $product['sku'] . ') - ' . $product['dimensions']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="group-rules">
                                <h4><?php _e('Regras de empilhamento:', 'woocommerce-stackable-shipping'); ?></h4>
                                <label>
                                    <input type="radio" name="stacking_groups[${groupCount}][stacking_rule]" value="any" checked>
                                    <?php _e('Qualquer produto deste grupo pode ser empilhado com qualquer outro do mesmo grupo', 'woocommerce-stackable-shipping'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="radio" name="stacking_groups[${groupCount}][stacking_rule]" value="same_only">
                                    <?php _e('Apenas produtos idênticos podem ser empilhados', 'woocommerce-stackable-shipping'); ?>
                                </label>
                            </div>
                            <button type="button" class="button remove-group"><?php _e('Remover Grupo', 'woocommerce-stackable-shipping'); ?></button>
                        </div>
                    `;
                    
                    $('#stacking-groups-container').append(template);
                    initSelectize();
                });
                
                $(document).on('click', '.remove-group', function() {
                    $(this).closest('.stacking-group').remove();
                });
                
                function initSelectize() {
                    $('.product-select').selectize({
                        plugins: ['remove_button'],
                        delimiter: ',',
                        persist: false,
                        create: false,
                        placeholder: '<?php _e('Selecione os produtos...', 'woocommerce-stackable-shipping'); ?>'
                    });
                }
                
                initSelectize();
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Exibe um grupo de empilhamento
 */
function display_stacking_group($group_id, $group, $products) {
    $group_name = isset($group['name']) ? $group['name'] : '';
    $max_items = isset($group['max_items']) ? $group['max_items'] : 5;
    $stacking_rule = isset($group['stacking_rule']) ? $group['stacking_rule'] : 'any';
    $selected_products = isset($group['products']) ? $group['products'] : array();
    ?>
    <div class="stacking-group" id="stacking-group-<?php echo esc_attr($group_id); ?>">
        <h3><?php _e('Grupo de Empilhamento', 'woocommerce-stackable-shipping'); ?> #<?php echo esc_html($group_id); ?></h3>
        <div class="group-settings">
            <label>
                <?php _e('Nome do Grupo:', 'woocommerce-stackable-shipping'); ?>
                <input type="text" name="stacking_groups[<?php echo esc_attr($group_id); ?>][name]" value="<?php echo esc_attr($group_name); ?>" placeholder="<?php esc_attr_e('Ex: Roupas pequenas', 'woocommerce-stackable-shipping'); ?>">
            </label>
            <label>
                <?php _e('Máximo de itens no grupo:', 'woocommerce-stackable-shipping'); ?>
                <input type="number" name="stacking_groups[<?php echo esc_attr($group_id); ?>][max_items]" value="<?php echo esc_attr($max_items); ?>" min="1" step="1">
            </label>
        </div>
        <div class="group-products">
            <h4><?php _e('Produtos neste grupo:', 'woocommerce-stackable-shipping'); ?></h4>
            <select name="stacking_groups[<?php echo esc_attr($group_id); ?>][products][]" multiple="multiple" class="product-select" style="width: 100%; min-height: 150px;">
                <?php foreach ($products as $product_id => $product) : ?>
                <option value="<?php echo esc_attr($product_id); ?>" <?php echo in_array($product_id, $selected_products) ? 'selected' : ''; ?>><?php echo esc_html($product['name'] . ' (' . $product['sku'] . ') - ' . $product['dimensions']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="group-rules">
            <h4><?php _e('Regras de empilhamento:', 'woocommerce-stackable-shipping'); ?></h4>
            <label>
                <input type="radio" name="stacking_groups[<?php echo esc_attr($group_id); ?>][stacking_rule]" value="any" <?php checked($stacking_rule, 'any'); ?>>
                <?php _e('Qualquer produto deste grupo pode ser empilhado com qualquer outro do mesmo grupo', 'woocommerce-stackable-shipping'); ?>
            </label>
            <br>
            <label>
                <input type="radio" name="stacking_groups[<?php echo esc_attr($group_id); ?>][stacking_rule]" value="same_only" <?php checked($stacking_rule, 'same_only'); ?>>
                <?php _e('Apenas produtos idênticos podem ser empilhados', 'woocommerce-stackable-shipping'); ?>
            </label>
        </div>
        <button type="button" class="button remove-group"><?php _e('Remover Grupo', 'woocommerce-stackable-shipping'); ?></button>
    </div>
    <?php
}

/**
 * Exibe a aba de exemplos
 */
function display_examples_tab() {
    ?>
    <div class="card">
        <h2><?php _e('Exemplos de Agrupamento', 'woocommerce-stackable-shipping'); ?></h2>
        <p><?php _e('Aqui estão alguns exemplos de como os produtos serão agrupados:', 'woocommerce-stackable-shipping'); ?></p>

        <div class="example-wrapper">
            <h3><?php _e('Exemplo 1: Produto único com várias unidades', 'woocommerce-stackable-shipping'); ?></h3>
            <div class="example-content">
                <div class="example-image">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/stack-example-1.png'); ?>" alt="<?php esc_attr_e('Exemplo de empilhamento', 'woocommerce-stackable-shipping'); ?>">
                </div>
                <div class="example-text">
                    <p>
                        <?php _e('Produto A (20×30×10 cm)', 'woocommerce-stackable-shipping'); ?><br>
                        <?php _e('Quantidade: 3', 'woocommerce-stackable-shipping'); ?><br>
                        <?php _e('Incremento de altura: 5 cm', 'woocommerce-stackable-shipping'); ?><br>
                        <strong><?php _e('Dimensões para frete: 20×30×20 cm', 'woocommerce-stackable-shipping'); ?></strong>
                    </p>
                    <p class="description">
                        <?php _e('Cálculo: Altura base (10 cm) + 2 incrementos de 5 cm cada = 20 cm', 'woocommerce-stackable-shipping'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="example-wrapper">
            <h3><?php _e('Exemplo 2: Produtos diferentes no mesmo grupo', 'woocommerce-stackable-shipping'); ?></h3>
            <div class="example-content">
                <div class="example-image">
                    <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__)) . 'assets/images/stack-example-2.png'); ?>" alt="<?php esc_attr_e('Exemplo de empilhamento misto', 'woocommerce-stackable-shipping'); ?>">
                </div>
                <div class="example-text">
                    <p>
                        <?php _e('Produto A (20×30×10 cm) - 2 unidades', 'woocommerce-stackable-shipping'); ?><br>
                        <?php _e('Produto B (20×30×8 cm) - 1 unidade', 'woocommerce-stackable-shipping'); ?><br>
                        <?php _e('Ambos no mesmo grupo de empilhamento', 'woocommerce-stackable-shipping'); ?><br>
                        <strong><?php _e('Dimensões para frete: 20×30×23 cm', 'woocommerce-stackable-shipping'); ?></strong>
                    </p>
                    <p class="description">
                        <?php _e('Cálculo: Altura do maior produto (10 cm) + incremento do Produto A (5 cm) + altura adicional do Produto B (8 cm) = 23 cm', 'woocommerce-stackable-shipping'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php
}