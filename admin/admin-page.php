<?php
defined('ABSPATH') || exit;

// Verifica permissões de acesso
if (!current_user_can('manage_woocommerce')) {
    wp_die(__('Você não tem permissões suficientes para acessar esta página.', 'woocommerce-stackable-shipping'));
}

add_filter('woocommerce_cart_shipping_packages', array($this, 'modify_shipping_packages'));

// Modifica as taxas de envio calculadas para um pacote
add_filter('woocommerce_package_rates', array($this, 'adjust_package_rates'), 10, 2);

// Altera o nome do pacote de envio (para mostrar que é um pacote agrupado)
add_filter('woocommerce_shipping_package_name', array($this, 'rename_shipping_package'), 10, 3);

// Permite modificar diretamente o custo de uma taxa de envio
add_filter('woocommerce_shipping_rate_cost', array($this, 'adjust_shipping_rate_cost'), 10, 2);

// Executa antes da exibição do calculador de frete
add_action('woocommerce_before_shipping_calculator', array($this, 'before_shipping_calculator'));

// Executa após a exibição dos métodos de envio disponíveis
add_action('woocommerce_after_shipping_rate', array($this, 'add_shipping_package_details'), 10, 2);
?>

<div class="wrap">
    <h1><?php echo esc_html(__('Agrupamento de Produtos para Frete', 'woocommerce-stackable-shipping')); ?></h1>

    <div class="notice notice-info">
        <p><?php _e('Configure como os produtos podem ser agrupados para cálculo de frete. Os produtos marcados como empilháveis serão organizados em grupos para otimizar as dimensões durante o cálculo do frete.', 'woocommerce-stackable-shipping'); ?></p>
    </div>

    <div class="card">
        <h2><?php _e('Como funciona', 'woocommerce-stackable-shipping'); ?></h2>
        <ol>
            <li><?php _e('Edite cada produto que pode ser empilhado e marque a opção "Produto Empilhável" na aba Envio', 'woocommerce-stackable-shipping'); ?></li>
            <li><?php _e('Defina o número máximo de unidades que podem ser empilhadas para cada produto', 'woocommerce-stackable-shipping'); ?></li>
            <li><?php _e('Configure o incremento de altura para cada unidade adicional empilhada', 'woocommerce-stackable-shipping'); ?></li>
        </ol>
        <p><?php _e('Durante o cálculo do frete, produtos empilháveis serão agrupados e suas dimensões ajustadas conforme as regras definidas.', 'woocommerce-stackable-shipping'); ?></p>
    </div>

    <div class="card">
        <h2><?php _e('Produtos Empilháveis', 'woocommerce-stackable-shipping'); ?></h2>
        <p><?php _e('Lista de produtos configurados como empilháveis:', 'woocommerce-stackable-shipping'); ?></p>

        <?php
        // Buscar produtos empilháveis
        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_is_stackable',
                    'value'   => 'yes',
                    'compare' => '='
                )
            )
        );

        $stackable_products = new WP_Query($args);
        ?>

        <?php if ($stackable_products->have_posts()) : ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Produto', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('SKU', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Dimensões Originais (LxCxA)', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Empilhamento Máximo', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Incremento de Altura', 'woocommerce-stackable-shipping'); ?></th>
                        <th><?php _e('Ações', 'woocommerce-stackable-shipping'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($stackable_products->have_posts()) : $stackable_products->the_post(); 
                        $product = wc_get_product(get_the_ID());
                        $max_stack = get_post_meta(get_the_ID(), '_max_stack_same', true);
                        $height_increment = get_post_meta(get_the_ID(), '_stack_height_increment', true);
                    ?>
                        <tr>
                            <td><?php echo esc_html($product->get_name()); ?></td>
                            <td><?php echo esc_html($product->get_sku()); ?></td>
                            <td>
                                <?php 
                                echo esc_html($product->get_width()) . ' × ' . 
                                     esc_html($product->get_length()) . ' × ' . 
                                     esc_html($product->get_height()) . ' ' . 
                                     esc_html(get_option('woocommerce_dimension_unit')); 
                                ?>
                            </td>
                            <td><?php echo esc_html($max_stack); ?></td>
                            <td>
                                <?php 
                                echo esc_html($height_increment) . ' ' . 
                                     esc_html(get_option('woocommerce_dimension_unit')); 
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('post.php?post=' . get_the_ID() . '&action=edit')); ?>" class="button">
                                    <?php _e('Editar', 'woocommerce-stackable-shipping'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e('Nenhum produto foi configurado como empilhável ainda.', 'woocommerce-stackable-shipping'); ?></p>
        <?php endif; ?>
        <?php wp_reset_postdata(); ?>

        <p>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=product')); ?>" class="button button-primary">
                <?php _e('Gerenciar Produtos', 'woocommerce-stackable-shipping'); ?>
            </a>
        </p>
    </div>

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
    </div>
</div>

<?php
/**
 * Modifica as taxas de envio após o cálculo
 */
public function adjust_package_rates($rates, $package) {
    // Aqui você pode ajustar as taxas com base nos agrupamentos
    return $rates;
}

/**
 * Renomeia o pacote de envio para indicar agrupamento
 */
public function rename_shipping_package($name, $i, $package) {
    // Verifica se o pacote possui produtos agrupados
    if (!empty($package['contents']) && $this->has_stackable_products($package)) {
        return sprintf(__('Pacote %d (com produtos empilhados)', 'woocommerce-stackable-shipping'), $i + 1);
    }
    return $name;
}

/**
 * Verifica se um pacote contém produtos empilháveis
 */
private function has_stackable_products($package) {
    if (empty($package['contents'])) {
        return false;
    }
    
    foreach ($package['contents'] as $item) {
        $product_id = $item['product_id'];
        $is_stackable = get_post_meta($product_id, '_is_stackable', true);
        if ('yes' === $is_stackable) {
            return true;
        }
    }
    
    return false;
}

/**
 * Adiciona detalhes sobre o agrupamento após cada método de envio
 */
public function add_shipping_package_details($method, $index) {
    // Exibe informações sobre o agrupamento de produtos para este método
    if ($this->has_stackable_products_in_cart()) {
        echo '<small class="stackable-notice">';
        echo __('Produtos empilháveis foram agrupados para otimizar o frete', 'woocommerce-stackable-shipping');
        echo '</small>';
    }
}

/**
 * Verifica se existem produtos empilháveis no carrinho
 */
private function has_stackable_products_in_cart() {
    if (WC()->cart) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $is_stackable = get_post_meta($product_id, '_is_stackable', true);
            if ('yes' === $is_stackable) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Adiciona informações antes do calculador de frete
 */
public function before_shipping_calculator() {
    if ($this->has_stackable_products_in_cart()) {
        echo '<div class="stackable-shipping-notice">';
        echo __('Alguns produtos no seu carrinho foram otimizados para cálculo de frete através de empilhamento.', 'woocommerce-stackable-shipping');
        echo '</div>';
    }
} 