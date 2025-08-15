<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para funcionalidades de pedidos
 */
class CDP_Order {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_admin_order_meta'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_customer_order_meta'));
        add_action('woocommerce_email_after_order_table', array($this, 'display_email_order_meta'), 10, 4);
    }
    
    /**
     * Exibir dimensões personalizadas no admin do pedido
     */
    public function display_admin_order_meta($order) {
        $has_custom_dimensions = false;
        
        foreach ($order->get_items() as $item_id => $item) {
            $custom_dimensions = $item->get_meta('_cdp_custom_dimensions');
            if ($custom_dimensions) {
                $has_custom_dimensions = true;
                break;
            }
        }
        
        if (!$has_custom_dimensions) {
            return;
        }
        ?>
        <div class="cdp-order-admin-meta">
            <h3><?php _e('Dimensões Personalizadas', 'custom-dimensions-pricing'); ?></h3>
            <style>
                .cdp-order-admin-meta {
                    margin: 20px 0;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 5px;
                    border-left: 4px solid #007cba;
                }
                .cdp-order-admin-meta h3 {
                    margin-top: 0;
                    color: #007cba;
                }
                .cdp-dimensions-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                .cdp-dimensions-table th,
                .cdp-dimensions-table td {
                    padding: 8px 12px;
                    text-align: left;
                    border-bottom: 1px solid #ddd;
                }
                .cdp-dimensions-table th {
                    background: #f1f1f1;
                    font-weight: 600;
                }
                .cdp-dimensions-table tr:hover {
                    background: #f9f9f9;
                }
            </style>
            
            <table class="cdp-dimensions-table">
                <thead>
                    <tr>
                        <th><?php _e('Produto', 'custom-dimensions-pricing'); ?></th>
                        <th><?php _e('Largura (cm)', 'custom-dimensions-pricing'); ?></th>
                        <th><?php _e('Altura (cm)', 'custom-dimensions-pricing'); ?></th>
                        <th><?php _e('Comprimento (cm)', 'custom-dimensions-pricing'); ?></th>
                        <th><?php _e('Dimensões Base', 'custom-dimensions-pricing'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item_id => $item) : 
                        $custom_dimensions = $item->get_meta('_cdp_custom_dimensions');
                        if ($custom_dimensions) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($item->get_name()); ?></strong></td>
                                <td><?php echo number_format($custom_dimensions['width'], 2, ',', '.'); ?></td>
                                <td><?php echo number_format($custom_dimensions['height'], 2, ',', '.'); ?></td>
                                <td><?php echo number_format($custom_dimensions['length'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php 
                                    // Obter dimensões base do produto WooCommerce
                                    $product = $item->get_product();
                                    if ($product) {
                                        echo sprintf(
                                            '%s x %s x %s',
                                            number_format((float) $product->get_width(), 2, ',', '.'),
                                            number_format((float) $product->get_height(), 2, ',', '.'),
                                            number_format((float) $product->get_length(), 2, ',', '.')
                                        );
                                    } else {
                                        echo sprintf(
                                            '%s x %s x %s',
                                            number_format($custom_dimensions['base_width'], 2, ',', '.'),
                                            number_format($custom_dimensions['base_height'], 2, ',', '.'),
                                            number_format($custom_dimensions['base_length'], 2, ',', '.')
                                        );
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; 
                    endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Exibir dimensões personalizadas para o cliente
     */
    public function display_customer_order_meta($order) {
        $has_custom_dimensions = false;
        
        foreach ($order->get_items() as $item_id => $item) {
            $custom_dimensions = $item->get_meta('_cdp_custom_dimensions');
            if ($custom_dimensions) {
                $has_custom_dimensions = true;
                break;
            }
        }
        
        if (!$has_custom_dimensions) {
            return;
        }
        ?>
        <div class="cdp-order-customer-meta">
            <h2><?php _e('Dimensões Personalizadas', 'custom-dimensions-pricing'); ?></h2>
            <style>
                .cdp-order-customer-meta {
                    margin: 30px 0;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border: 1px solid #e9ecef;
                }
                .cdp-order-customer-meta h2 {
                    margin-top: 0;
                    color: #495057;
                    font-size: 20px;
                    margin-bottom: 15px;
                }
                .cdp-customer-dimensions-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }
                .cdp-customer-dimensions-item {
                    background: white;
                    padding: 15px;
                    margin-bottom: 10px;
                    border-radius: 6px;
                    border-left: 4px solid #007cba;
                }
                .cdp-customer-dimensions-item h4 {
                    margin: 0 0 10px 0;
                    color: #007cba;
                    font-size: 16px;
                }
                .cdp-customer-dimensions-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                    gap: 10px;
                    margin-top: 10px;
                }
                .cdp-customer-dimension-item {
                    text-align: center;
                    padding: 8px;
                    background: #f8f9fa;
                    border-radius: 4px;
                }
                .cdp-customer-dimension-label {
                    font-size: 12px;
                    color: #6c757d;
                    margin-bottom: 3px;
                }
                .cdp-customer-dimension-value {
                    font-weight: 600;
                    color: #495057;
                }
            </style>
            
            <ul class="cdp-customer-dimensions-list">
                <?php foreach ($order->get_items() as $item_id => $item) : 
                    $custom_dimensions = $item->get_meta('_cdp_custom_dimensions');
                    if ($custom_dimensions) : ?>
                        <li class="cdp-customer-dimensions-item">
                            <h4><?php echo esc_html($item->get_name()); ?></h4>
                            <div class="cdp-customer-dimensions-grid">
                                <div class="cdp-customer-dimension-item">
                                    <div class="cdp-customer-dimension-label"><?php _e('Largura', 'stackable-product-shipping'); ?></div>
                                    <div class="cdp-customer-dimension-value"><?php echo number_format($custom_dimensions['width'], 2, ',', '.'); ?> cm</div>
                                </div>
                                <div class="cdp-customer-dimension-item">
                                    <div class="cdp-customer-dimension-label"><?php _e('Altura', 'stackable-product-shipping'); ?></div>
                                    <div class="cdp-customer-dimension-value"><?php echo number_format($custom_dimensions['height'], 2, ',', '.'); ?> cm</div>
                                </div>
                                <div class="cdp-customer-dimension-item">
                                    <div class="cdp-customer-dimension-label"><?php _e('Comprimento', 'stackable-product-shipping'); ?></div>
                                    <div class="cdp-customer-dimension-value"><?php echo number_format($custom_dimensions['length'], 2, ',', '.'); ?> cm</div>
                                </div>
                            </div>
                        </li>
                    <?php endif; 
                endforeach; ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Exibir dimensões personalizadas nos emails
     */
    public function display_email_order_meta($order, $sent_to_admin, $plain_text, $email) {
        $has_custom_dimensions = false;
        
        foreach ($order->get_items() as $item_id => $item) {
            $custom_dimensions = $item->get_meta('_cdp_custom_dimensions');
            if ($custom_dimensions) {
                $has_custom_dimensions = true;
                break;
            }
        }
        
        if (!$has_custom_dimensions) {
            return;
        }
        
        if ($plain_text) {
            echo "\n" . __('DIMENSÕES PERSONALIZADAS', 'stackable-product-shipping') . "\n";
            echo str_repeat('-', 50) . "\n";
            
            foreach ($order->get_items() as $item_id => $item) {
                $custom_dimensions = $item->get_meta('_cdp_custom_dimensions');
                if ($custom_dimensions) {
                    echo $item->get_name() . "\n";
                    echo sprintf(
                        __('Dimensões: %s x %s x %s cm (L x A x C)', 'stackable-product-shipping'),
                        number_format($custom_dimensions['width'], 2, ',', '.'),
                        number_format($custom_dimensions['height'], 2, ',', '.'),
                        number_format($custom_dimensions['length'], 2, ',', '.')
                    ) . "\n\n";
                }
            }
        } else {
            ?>
            <div style="margin: 30px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h2 style="margin-top: 0; color: #495057; font-size: 18px; margin-bottom: 15px;">
                    <?php _e('Dimensões Personalizadas', 'stackable-product-shipping'); ?>
                </h2>
                
                <?php foreach ($order->get_items() as $item_id => $item) : 
                    $custom_dimensions = $item->get_meta('_cdp_custom_dimensions');
                    if ($custom_dimensions) : ?>
                        <div style="background: white; padding: 15px; margin-bottom: 10px; border-radius: 6px; border-left: 4px solid #007cba;">
                            <h4 style="margin: 0 0 10px 0; color: #007cba; font-size: 16px;">
                                <?php echo esc_html($item->get_name()); ?>
                            </h4>
                            <p style="margin: 0; color: #495057;">
                                <strong><?php _e('Dimensões:', 'stackable-product-shipping'); ?></strong>
                                <?php echo sprintf(
                                    __('%s x %s x %s cm (L x A x C)', 'stackable-product-shipping'),
                                    number_format($custom_dimensions['width'], 2, ',', '.'),
                                    number_format($custom_dimensions['height'], 2, ',', '.'),
                                    number_format($custom_dimensions['length'], 2, ',', '.')
                                ); ?>
                            </p>
                        </div>
                    <?php endif; 
                endforeach; ?>
            </div>
            <?php
        }
    }
}