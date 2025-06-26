<?php
/**
 * Admin Main Page for Stackable Product Shipping
 */
class SPS_Admin_Main {
    /**
     * Render the main admin page
     */
    public static function render_page() {
        // Get plugin version
        $version = defined('SPS_VERSION') ? SPS_VERSION : 'v1.0.0';
        
        // Get statistics
        global $wpdb;
        $groups_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sps_groups WHERE stacking_type = 'multiple'");
        $products_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}sps_groups WHERE stacking_type = 'single'");
        
        // Get recent groups
        $recent_groups = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}sps_groups WHERE stacking_type = 'multiple' ORDER BY id DESC LIMIT 5", ARRAY_A);
        
        // Rest of the function remains the same
        // Enqueue the admin CSS file
        wp_enqueue_style('sps-admin-style', plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/sps-admin.css', [], $version);
        ?>
        <div class="wrap sps-admin-wrap">
            <div class="sps-header">
                <h1>Empilhamento de Produtos</h1>
                <span class="sps-version">Versão <?php echo esc_html($version); ?></span>
            </div>
            
            <div class="sps-admin-content">
                <!-- Welcome Section -->
                <div class="sps-welcome-panel">
                    <div class="sps-welcome-panel-content">
                        <h2>Bem-vindo ao Empilhamento de Produtos</h2>
                        <p class="about-description">Este plugin permite criar grupos de produtos empilháveis para otimizar o cálculo de frete.</p>
                        
                        <div class="sps-welcome-panel-column-container">
                            <div class="sps-welcome-panel-column">
                                <h3>Começar</h3>
                                <a href="<?php echo admin_url('admin.php?page=sps-create'); ?>" class="button button-primary button-hero">Criar Novo Grupo</a>
                            </div>
                            <div class="sps-welcome-panel-column">
                                <h3>Próximos Passos</h3>
                                <ul>
                                    <li><a href="<?php echo admin_url('admin.php?page=sps-group-products'); ?>" class="sps-welcome-icon sps-welcome-view-groups">Gerenciar grupos existentes</a></li>
                                    <li><a href="<?php echo admin_url('admin.php?page=sps-stackable-products'); ?>" class="sps-welcome-icon sps-welcome-products">Configurar produtos empilháveis</a></li>
                                    <li><a href="<?php echo admin_url('admin.php?page=sps-settings'); ?>" class="sps-welcome-icon sps-welcome-settings">Ajustar configurações</a></li>
                                </ul>
                            </div>
                            <div class="sps-welcome-panel-column sps-welcome-panel-last">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Dashboard Widgets -->
                <div class="sps-dashboard-widgets">
                    <!-- Statistics Widget -->
                    <div class="sps-dashboard-widget">
                        <div class="sps-dashboard-widget-header">
                            <h2><span class="dashicons dashicons-chart-bar"></span> Estatísticas</h2>
                        </div>
                        <div class="sps-dashboard-widget-content">
                            <div class="sps-stats-grid">
                                <div class="sps-stat-card">
                                    <a href="<?php echo admin_url('admin.php?page=sps-group-products'); ?>" style="text-decoration: none; color: inherit;">
                                        <div class="sps-stat-icon">
                                            <span class="dashicons dashicons-groups"></span>
                                        </div>
                                        <div class="sps-stat-content">
                                            <span class="sps-stat-number"><?php echo intval($groups_count); ?></span>
                                            <span class="sps-stat-label">Grupos Salvos</span>
                                        </div>
                                    </a>
                                </div>
                                <div class="sps-stat-card">
                                    <a href="<?php echo admin_url('admin.php?page=sps-stackable-products'); ?>" style="text-decoration: none; color: inherit;">
                                        <div class="sps-stat-icon">
                                            <span class="dashicons dashicons-products"></span>
                                        </div>
                                        <div class="sps-stat-content">
                                            <span class="sps-stat-number"><?php echo intval($products_count); ?></span>
                                            <span class="sps-stat-label">Produtos Empilháveis</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Groups Widget -->
                    <div class="sps-dashboard-widget">
                        <div class="sps-dashboard-widget-header">
                            <h2><span class="dashicons dashicons-list-view"></span> Grupos Recentes</h2>
                        </div>
                        <div class="sps-dashboard-widget-content">
                            <?php if (empty($recent_groups)): ?>
                                <p class="sps-no-items">Nenhum grupo criado ainda. <a href="<?php echo admin_url('admin.php?page=sps-create'); ?>">Criar o primeiro grupo</a>.</p>
                            <?php else: ?>
                                <ul class="sps-recent-list">
                                    <?php foreach ($recent_groups as $group): ?>
                                        <li>
                                            <a href="<?php echo admin_url('admin.php?page=sps-create&edit=' . $group['id']); ?>">
                                                <?php echo esc_html($group['name']); ?>
                                            </a>
                                            <div class="row-actions">
                                                <span class="edit">
                                                    <a href="<?php echo admin_url('admin.php?page=sps-create&edit=' . $group['id']); ?>">Editar</a> | 
                                                </span>
                                                <span class="view">
                                                    <a href="#" class="sps-view-group" data-id="<?php echo $group['id']; ?>">Visualizar</a>
                                                </span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <p class="sps-view-all">
                                    <a href="<?php echo admin_url('admin.php?page=sps-groups'); ?>">Ver todos os grupos →</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions Widget -->
                    <div class="sps-dashboard-widget">
                        <div class="sps-dashboard-widget-header">
                            <h2><span class="dashicons dashicons-admin-tools"></span> Ações Rápidas</h2>
                        </div>
                        <div class="sps-dashboard-widget-content">
                            <div class="sps-quick-actions">
                                <a href="<?php echo admin_url('admin.php?page=sps-create'); ?>" class="sps-quick-action-button">
                                    <span class="dashicons dashicons-plus"></span>
                                    <span>Novo Grupo</span>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=sps-stackable-products'); ?>" class="sps-quick-action-button">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    <span>Configurar Produtos</span>
                                </a>
                                <a href="<?php echo admin_url('admin.php?page=sps-settings'); ?>" class="sps-quick-action-button">
                                    <span class="dashicons dashicons-admin-settings"></span>
                                    <span>Configurações</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}