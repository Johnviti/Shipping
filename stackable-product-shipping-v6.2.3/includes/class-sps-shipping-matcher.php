<?php
class SPS_Shipping_Matcher {

    public static function get_groups_from_db() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        $groups = [];
        foreach ($results as $row) {
            $groups[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'product_ids' => json_decode($row['product_ids'], true) ?: explode(',', $row['product_ids']),
                'quantities' => json_decode($row['quantities'], true) ?: explode(',', $row['quantities']),
                'stacking_ratio' => isset($row['stacking_ratio']) ? $row['stacking_ratio'] : 0,
                'weight' => isset($row['weight']) ? $row['weight'] : 0,
                'height' => isset($row['height']) ? $row['height'] : 0,
                'width'  => isset($row['width'])  ? $row['width']  : 0,
                'length' => isset($row['length']) ? $row['length'] : 0,
            ];
        }
        return $groups;
    }

    public static function match_cart_with_groups($cart_items) {
        $groups = self::get_groups_from_db();
        $cart_map = [];
        foreach ($cart_items as $key => $item) {
            $cart_map[$item['product_id']] = $item['quantity'];
        }
        $packages = [];
        foreach ($groups as $group) {
            $g_map = [];
            foreach ($group['product_ids'] as $i => $pid) {
                $g_map[intval($pid)] = intval($group['quantities'][$i]);
            }
            $min_fits = PHP_INT_MAX;
            foreach ($g_map as $pid => $qtd) {
                if (!isset($cart_map[$pid]) || $cart_map[$pid] < $qtd) {
                    $min_fits = 0;
                    break;
                }
                $min_fits = min($min_fits, intdiv($cart_map[$pid], $qtd));
            }
            for ($i = 0; $i < $min_fits; $i++) {
                $packages[] = [
                    'group' => $group,
                    'products' => $g_map
                ];
                foreach ($g_map as $pid => $qtd) {
                    $cart_map[$pid] -= $qtd;
                    if ($cart_map[$pid] <= 0) {
                        unset($cart_map[$pid]);
                    }
                }
            }
        }
        // O que sobrar em $cart_map são itens avulsos
        $avulsos = [];
        foreach ($cart_items as $item) {
            if (isset($cart_map[$item['product_id']]) && $cart_map[$item['product_id']] > 0) {
                $item_copy = $item;
                $item_copy['quantity'] = $cart_map[$item['product_id']]; // só o excedente!
                $avulsos[] = $item_copy;
                unset($cart_map[$item['product_id']]);
            }
        }
        return [$packages, $avulsos];
    }
}
