<?php
class SPS_Shipping_Matcher {

    public static function get_groups_from_db() {
        global $wpdb;
        $table = $wpdb->prefix . 'sps_groups';
        $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
        $groups = [];

        foreach ($results as $row) {
            $product_ids = json_decode($row['product_ids'], true);
            $quantities = json_decode($row['quantities'], true);

            if (!is_array($product_ids)) {
                if (preg_match('/^\[(.*)\]$/', $row['product_ids'], $matches)) {
                    $product_ids = explode(',', $matches[1]);
                } else {
                    $product_ids = explode(',', $row['product_ids']);
                }
            }

            if (!is_array($quantities)) {
                if (preg_match('/^\[(.*)\]$/', $row['quantities'], $matches)) {
                    $quantities = explode(',', $matches[1]);
                } else {
                    $quantities = explode(',', $row['quantities']);
                }
            }

            $product_ids = array_map('intval', $product_ids);
            $quantities = array_map('intval', $quantities);

            $grouped_products = array_combine($product_ids, $quantities);

            $groups[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'products' => $grouped_products,
                'product_ids' => $product_ids,  // Adiciona explicitamente para uso no backtrack_combinations
                'quantities' => $quantities,    // Adiciona explicitamente para uso no backtrack_combinations
                'stacking_ratio' => $row['stacking_ratio'] ?? 0,
                'weight' => $row['weight'] ?? 0,
                'height' => $row['height'] ?? 0,
                'width'  => $row['width']  ?? 0,
                'length' => $row['length'] ?? 0,
                'stacking_type' => $row['stacking_type'] ?? 'multiple',
                'height_increment' => $row['height_increment'] ?? 0,
                'width_increment'  => $row['width_increment'] ?? 0,
                'length_increment' => $row['length_increment'] ?? 0,
                'max_quantity'     => $row['max_quantity'] ?? 0,
            ];
        }

        return $groups;
    }

    public static function match_cart_with_groups($cart_items) {
        error_log("Iniciando combinação de grupos para o carrinho.");

        $groups = self::get_groups_from_db();
        $cart_map = self::convert_cart_to_map($cart_items);
        $combinations = self::generate_all_combinations($cart_map, $groups);

        $best_combination = null;
        $best_volume = PHP_INT_MAX;

        foreach ($combinations as $combo) {
            $volume = self::calculate_total_volume($combo['packages'], $combo['avulsos']);

            if ($volume < $best_volume) {
                $best_volume = $volume;
                $best_combination = $combo;
            }
        }

        return $best_combination;
    }

    private static function convert_cart_to_map($cart_items) {
        $map = [];
        foreach ($cart_items as $item) {
            $pid = $item['product_id'];
            $qty = intval($item['quantity']);
            $map[$pid] = ($map[$pid] ?? 0) + $qty;
        }
        return $map;
    }

    private static function calculate_total_volume($packages, $avulsos) {
        $total_volume = 0;

        foreach ($packages as $pkg) {
            $group = $pkg['group'];
            $qty = $pkg['quantity'];

            if ($group['stacking_type'] === 'single') {
                $max_qtd = min($qty, $group['max_quantity'] ?: $qty);

                $height = $group['height'] + ($group['height_increment'] * ($max_qtd - 1));
                $width  = $group['width']  + ($group['width_increment']  * ($max_qtd - 1));
                $length = $group['length'] + ($group['length_increment'] * ($max_qtd - 1));
            } else {
                $height = $group['height'];
                $width  = $group['width'];
                $length = $group['length'];
            }

            $volume = ($height / 100) * ($width / 100) * ($length / 100);
            $total_volume += $volume;
        }

        foreach ($avulsos as $item) {
            $volume = ($item['height'] / 100) * ($item['width'] / 100) * ($item['length'] / 100) * $item['quantity'];
            $total_volume += $volume;
        }

        return $total_volume;
    }

    private static function generate_all_combinations($cart_map, $groups) {
        $combinations = [];
        self::backtrack_combinations($cart_map, $groups, [], $combinations);
        return $combinations;
    }

    private static function backtrack_combinations($remaining_map, $groups, $current_combination, &$combinations, $depth = 0) {
        $any_used = false;
    
        foreach ($groups as $group) {
            $product_ids = $group['product_ids'];
            $quantities  = $group['quantities'];
    
            $max_possible = PHP_INT_MAX;
    
            for ($i = 0; $i < count($product_ids); $i++) {
                $pid = $product_ids[$i];
                $qty_needed = $quantities[$i];
    
                if (!isset($remaining_map[$pid]) || $remaining_map[$pid] < $qty_needed) {
                    $max_possible = 0;
                    break;
                }
    
                $max_possible = min($max_possible, intdiv($remaining_map[$pid], $qty_needed));
            }
    
            if ($max_possible === 0) continue;
    
            // Limita o número de pacotes com base no max_quantity (empilhamento individual)
            $group_limit = $group['max_quantity'] ?: $max_possible;
            $use_limit = min($max_possible, $group_limit);
    
            // Tenta aplicar o grupo de 1 até use_limit vezes
            for ($count = 1; $count <= $use_limit; $count++) {
                $new_remaining = $remaining_map;
                $can_apply = true;
    
                // Verifica e aplica consumo proporcional dos produtos
                for ($i = 0; $i < count($product_ids); $i++) {
                    $pid = $product_ids[$i];
                    $qty_needed = $quantities[$i] * $count;
    
                    if (!isset($new_remaining[$pid]) || $new_remaining[$pid] < $qty_needed) {
                        $can_apply = false;
                        break;
                    }
    
                    $new_remaining[$pid] -= $qty_needed;
                    if ($new_remaining[$pid] === 0) {
                        unset($new_remaining[$pid]);
                    }
                }
    
                if (!$can_apply) {
                    continue;
                }
    
                // Monta nova combinação parcial
                $new_combination = $current_combination;
                $new_combination[] = [
                    'group' => $group,
                    'products' => $group['products'],
                    'quantity' => $count,
                ];
    
                // Recursivamente tenta novas combinações com o restante
                self::backtrack_combinations($new_remaining, $groups, $new_combination, $combinations, $depth + 1);
                $any_used = true;
            }
        }
    
        // Se nenhum grupo puder ser usado, registra a combinação final com os produtos restantes como avulsos
        if (!$any_used) {
            $avulsos = [];
            foreach ($remaining_map as $pid => $qty) {
                $product = wc_get_product($pid);
                $avulsos[] = [
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'height' => $product->get_height(),
                    'width'  => $product->get_width(),
                    'length' => $product->get_length(),
                    'weight' => $product->get_weight(),
                ];
            }
    
            $combinations[] = [
                'packages' => $current_combination,
                'avulsos' => $avulsos,
            ];
        }
    }
       
}
