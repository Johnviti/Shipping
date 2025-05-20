<?php
class SPS_Ajax {
    public function __construct() {
        add_action('wp_ajax_sps_search_products', [$this,'search_products']);
        add_action('wp_ajax_sps_calculate_weight', [$this,'calculate_weight']);
    }

    public function search_products() {
        // No nonce for compatibility with v10
        if (!current_user_can('manage_options')) wp_die();
        $term = isset($_GET['term'])?sanitize_text_field($_GET['term']):'';
        $query = new WP_Query([
            'post_type'=>'product',
            's'=>$term,
            'posts_per_page'=>20
        ]);
        $results = [];
        foreach($query->posts as $p){
            $results[] = [
                'id'=>$p->ID,
                'text'=>$p->post_title . ' (ID: ' . $p->ID . ')'
            ];
        }
        wp_send_json($results);
    }

    public function calculate_weight() {
        if (!current_user_can('manage_options')) wp_die();
        $products = isset($_POST['products']) && is_array($_POST['products'])?array_map('intval',$_POST['products']):[];
        $quantities = isset($_POST['quantities']) && is_array($_POST['quantities'])?array_map('intval',$_POST['quantities']):[];
        $total = 0;
        foreach($products as $i=>$pid){
            $prod = wc_get_product($pid);
            if($prod){
                $total += floatval($prod->get_weight()) * ($quantities[$i]??1);
            }
        }
        wp_send_json(['total_weight'=>$total]);
    }
}
