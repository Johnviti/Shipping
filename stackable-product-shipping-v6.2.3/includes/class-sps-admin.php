<?php
class SPS_Admin {
    public static function register_menu() {
        add_menu_page('Empilhamento','Empilhamento','manage_options','sps-main',[__CLASS__,'main_page'],'dashicons-align-center',56);
        add_submenu_page('sps-main','Criar Novo','Criar Novo','manage_options','sps-create',[__CLASS__,'create_page']);
        add_submenu_page('sps-main','Grupos Salvos','Grupos Salvos','manage_options','sps-groups',[__CLASS__,'groups_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    public static function enqueue_scripts($hook) {
        if(strpos($hook,'sps-')===false) return;
        wp_enqueue_script('select2','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',['jquery'],null,true);
        wp_enqueue_style('select2-css','https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('sps-admin-js',SPS_PLUGIN_URL.'assets/js/sps-admin.js',['jquery','select2','jquery-ui-sortable'],null,true);
        wp_enqueue_style('sps-admin-css', SPS_PLUGIN_URL.'assets/css/sps-admin.css');
    }

    public static function main_page() {
        echo '<div class="wrap"><h1>Empilhamento</h1><p>Gerencie os grupos de empilhamento criados.</p></div>';
    }

    public static function create_page() {
        // Show notice after redirect
        if (isset($_GET['message']) && $_GET['message'] === 'added') {
            echo '<div class="notice notice-success is-dismissible"><p>Salvo com sucesso!</p></div>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $editing = false;
        $group = ['name'=>'','product_ids'=>[],'quantities'=>[],'stacking_ratio'=>'','weight'=>'','height'=>'','width'=>'','length'=>''];

        if(isset($_GET['edit'])) {
            $editing = true;
            $id = intval($_GET['edit']);
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            if($row) {
                $group['name'] = $row['name'];
                $group['product_ids'] = json_decode($row['product_ids'], true) ?: [];
                $group['quantities'] = json_decode($row['quantities'], true) ?: [];
                $group['stacking_ratio'] = $row['stacking_ratio'];
                $group['weight'] = $row['weight'];
                $group['height'] = $row['height'];
                $group['width'] = $row['width'];
                $group['length'] = $row['length'];
            }
        }

        if(isset($_POST['sps_save_group'])) {
            check_admin_referer('sps_save_group');
            $name = sanitize_text_field($_POST['sps_group_name']);
            $product_ids = array_map('intval', $_POST['sps_product_id']);
            $quantities = array_map('intval', $_POST['sps_product_quantity']);
            $stacking_ratio = floatval($_POST['sps_group_stacking_ratio']);
            $weight = floatval($_POST['sps_group_weight']);
            $height = floatval($_POST['sps_group_height']);
            $width = floatval($_POST['sps_group_width']);
            $length = floatval($_POST['sps_group_length']);

            $data = ['name'=>$name,'product_ids'=>json_encode($product_ids),'quantities'=>json_encode($quantities),
                     'stacking_ratio'=>$stacking_ratio,'weight'=>$weight,'height'=>$height,'width'=>$width,'length'=>$length];

            if($editing) {
                $wpdb->update($table, $data, ['id'=>$id]);
                echo '<div class="notice notice-success"><p>Editado com sucesso!</p></div>';
            } else {
                $wpdb->insert($table, $data);
                // After new save, redirect to clear form
                wp_redirect(admin_url('admin.php?page=sps-create&message=added'));
                exit;
            }
            $group = array_merge($group, $data);
            $group['product_ids'] = $product_ids;
            $group['quantities'] = $quantities;
        }

        // FORMULÁRIO:
        ?>
        <div class="wrap">
            <h1><?php echo $editing ? 'Editar Empilhamento' : 'Novo Empilhamento'; ?></h1>
            <form method="post">
                <?php wp_nonce_field('sps_save_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="sps_group_name">Nome do Grupo</label></th>
                        <td><input name="sps_group_name" id="sps_group_name" value="<?php echo esc_attr($group['name']); ?>" required class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Produtos a serem empilhados</th>
                        <td>
                            <table id="sps-products-table" class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th style="width:60%">Produto</th>
                                        <th style="width:20%">Quantidade</th>
                                        <th style="width:10%">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $count = max(count($group['product_ids']), 1);
                                    for($i=0; $i<$count; $i++): ?>
                                    <tr class="sps-product-row">
                                        <td>
                                            <select name="sps_product_id[]" class="sps-product-select" style="width:100%" required>
                                                <?php if(! empty($group['product_ids'][$i])): 
                                                    $p = wc_get_product($group['product_ids'][$i]);
                                                    echo '<option value="'.esc_attr($group['product_ids'][$i]).'" selected>'.esc_html($p->get_name()).'</option>';
                                                endif; ?>
                                            </select>
                                        </td>
                                        <td><input type="number" name="sps_product_quantity[]" class="small-text" value="<?php echo esc_attr($group['quantities'][$i] ?? 1); ?>" min="1" required></td>
                                        <td>
                                            <?php if($i>0): ?>
                                                <button type="button" class="button sps-remove-product"><span class="dashicons dashicons-trash"></span></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                            <p>
                                <button type="button" id="sps-add-product" id="sps-add-product" class="button">+ Adicionar Produto</button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sps_group_stacking_ratio">Fator de Empilhamento</label></th>
                        <td>
                            <input type="number" step="0.01" name="sps_group_stacking_ratio" id="sps_group_stacking_ratio" value="<?php echo esc_attr($group['stacking_ratio']); ?>">
                            <p class="description">Se preencher as dimensões abaixo, este valor será ignorado.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sps_group_weight">Peso Total (kg)</label></th>
                        <td><input type="number" step="0.01" name="sps_group_weight" id="sps_group_weight" value="<?php echo esc_attr($group['weight']); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label>Dimensões (Altura x Largura x Comprimento em cm)</label></th>
                        <td>
                            <input type="number" step="0.01" name="sps_group_height" value="<?php echo esc_attr($group['height']); ?>" placeholder="Altura"> ×
                            <input type="number" step="0.01" name="sps_group_width" value="<?php echo esc_attr($group['width']); ?>" placeholder="Largura"> ×
                            <input type="number" step="0.01" name="sps_group_length" value="<?php echo esc_attr($group['length']); ?>" placeholder="Comprimento">
                        </td>
                    </tr>
                </table>
                <?php submit_button($editing ? 'Atualizar Empilhamento' : 'Salvar Empilhamento', 'primary', 'sps_save_group'); ?>
            </form>
        </div>
        <?php
    }

    public static function groups_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';

        // Delete group if requested
        if (!empty($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id']) && check_admin_referer('sps_delete_' . intval($_GET['id']))) {
            $wpdb->delete($table, ['id' => intval($_GET['id'])]);
            echo '<div class="notice notice-success"><p>Grupo excluído com sucesso!</p></div>';
        }

        $groups = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1>Grupos Salvos</h1> <a href='<?php echo admin_url('admin.php?page=sps-create'); ?>' class='page-title-action'>Criar Novo Grupo</a>
            <input type="text" id="sps-groups-search" class="regular-text" placeholder="Pesquisar grupos..." style="margin-bottom:10px;">
            <table id="sps-groups-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Peso (kg)</th>
                        <th>Dimensões (A × L × C)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($groups as $g): ?>
                        <tr>
                            <td><?php echo esc_html($g->id); ?></td>
                            <td><?php echo esc_html($g->name); ?></td>
                            <td><?php echo esc_html($g->weight); ?></td>
                            <td><?php echo esc_html($g->height).' × '.esc_html($g->width).' × '.esc_html($g->length); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'sps-create','edit'=>$g->id], admin_url('admin.php'))); ?>">✎</a>
                                <a class="button sps-delete" onclick="return confirm('Tem certeza que deseja excluir este grupo?');" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page'=>'sps-groups','action'=>'delete','id'=>$g->id], admin_url('admin.php')), 'sps_delete_'.$g->id)); ?>">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
