<?php
if(!class_exists('WP_List_Table')) require_once ABSPATH.'wp-admin/includes/class-wp-list-table.php';

class SPS_Groups_Table extends WP_List_Table {
    public function __construct(){
        parent::__construct(['singular'=>'sps_group','plural'=>'sps_groups','ajax'=>false]);
    }

    public static function get_data(){
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sps_groups", ARRAY_A);
        return array_map(function($r){
            $ids = json_decode($r['product_ids'], true);
            if (!is_array($ids)) {
                $ids = array_filter(array_map('intval',explode(',',$r['product_ids'])));
            }
            $qtys = json_decode($r['quantities'], true);
            if (!is_array($qtys)) {
                $qtys = array_filter(array_map('intval',explode(',',$r['quantities'])));
            }
            $r['product_ids'] = $ids;
            $r['quantities'] = $qtys;
            return $r;
        }, $rows);
    }

    public function get_columns(){
        return [
            'cb'=>'<input type="checkbox"/>','name'=>'Nome','products'=>'Produtos',
            'weight'=>'Peso','stacking_ratio'=>'Fator','actions'=>'Ações'
        ];
    }

    protected function column_cb($item){
        return sprintf('<input type="checkbox" name="bulk[]" value="%d"/>',$item['id']);
    }

    protected function column_products($item){
        $out=[];
        if(is_array($item['product_ids'])){
            foreach($item['product_ids'] as $i=>$id){
                $p=wc_get_product($id);
                $name=$p?$p->get_name():"ID $id";
                $out[]=$name.' x'.($item['quantities'][$i]??1);
            }
        }
        return implode('<br>',$out);
    }

    protected function column_actions($item){
        $edit=add_query_arg(['page'=>'sps-create','edit'=>$item['id']],admin_url('admin.php'));
        $del=wp_nonce_url(add_query_arg(['page'=>'sps-groups','action'=>'delete','id'=>$item['id']],admin_url('admin.php')),'sps_delete_'.$item['id']);
        return sprintf('<a href="%s">✎</a> <a href="%s">🗑️</a>',esc_url($edit),esc_url($del));
    }

    public function prepare_items(){
        $columns=$this->get_columns();
        $hidden=[]; $sortable=[];
        $this->_column_headers=[$columns,$hidden,$sortable];
        $this->items=self::get_data();
    }
}
