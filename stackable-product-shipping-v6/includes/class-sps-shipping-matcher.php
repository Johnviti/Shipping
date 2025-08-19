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
                'product_ids' => $product_ids,
                'quantities' => $quantities,
                'stacking_ratio' => $row['stacking_ratio'] ?? 0,
                'weight' => $row['weight'] ?? 0,
                'height' => $row['height'] ?? 0,
                'width'  => $row['width']  ?? 0,
                'length' => $row['length'] ?? 0,
                'stacking_type' => $row['stacking_type'] ?? 'single',
                'height_increment' => $row['height_increment'] ?? 0,
                'width_increment'  => $row['width_increment'] ?? 0,
                'length_increment' => $row['length_increment'] ?? 0,
                'max_quantity'     => $row['max_quantity'] ?? 0,
            ];
        }

        return $groups;
    }

    /**
     * Novo método principal que retorna um único pacote com todos os itens
     * Grupos são tratados como itens únicos com suas dimensões definidas
     * Produtos fora de grupos são incluídos como itens individuais
     * Tudo é empacotado em um único pacote com empilhamento tipo "single"
     */
    public static function match_cart_with_groups($cart_items) {
        error_log("SPS: Iniciando agrupamento para pacote único");

        $groups = self::get_groups_from_db();
        $cart_map = self::convert_cart_to_map($cart_items);
        
        // Encontra a melhor combinação de grupos
        $best_combination = self::find_best_group_combination($cart_map, $groups);
        
        // Calcula as dimensões e peso do pacote único
        $single_package = self::calculate_single_package_dimensions($best_combination, $cart_items);
        
        return $single_package;
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

    /**
     * Encontra a melhor combinação de grupos que maximiza o uso de produtos
     */
    private static function find_best_group_combination($cart_map, $groups) {
        $combinations = [];
        self::backtrack_combinations($cart_map, $groups, [], $combinations);
        
        if (empty($combinations)) {
            // Se não há combinações, todos os produtos são avulsos
            return [
                'group_items' => [],
                'individual_items' => $cart_map
            ];
        }

        // Encontra a combinação que usa mais produtos em grupos
        $best_combination = null;
        $best_grouped_count = 0;

        foreach ($combinations as $combo) {
            $grouped_count = 0;
            foreach ($combo['packages'] as $pkg) {
                $grouped_count += array_sum($pkg['products']) * $pkg['quantity'];
            }
            
            if ($grouped_count > $best_grouped_count) {
                $best_grouped_count = $grouped_count;
                $best_combination = $combo;
            }
        }

        return [
            'group_items' => $best_combination['packages'],
            'individual_items' => $best_combination['avulsos_map'] ?? []
        ];
    }

    private static function backtrack_combinations($remaining_map, $groups, $current_combination, &$combinations, $depth = 0) {
        $any_used = false;
    
        foreach ($groups as $group) {
            $product_ids = $group['product_ids'];
            $quantities  = $group['quantities'];
    
            // Calcula quantas vezes este grupo pode ser aplicado
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
    
            // Tenta aplicar o grupo múltiplas vezes
            for ($count = 1; $count <= $max_possible; $count++) {
                $new_remaining = $remaining_map;
                $can_apply = true;
    
                // Consome os produtos do carrinho
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
    
                if (!$can_apply) continue;
    
                // Adiciona este grupo à combinação
                $new_combination = $current_combination;
                $new_combination[] = [
                    'group' => $group,
                    'products' => $group['products'],
                    'quantity' => $count,
                ];
    
                // Continua recursivamente
                self::backtrack_combinations($new_remaining, $groups, $new_combination, $combinations, $depth + 1);
                $any_used = true;
            }
        }
    
        // Se nenhum grupo foi usado, registra a combinação final
        if (!$any_used) {
            $combinations[] = [
                'packages' => $current_combination,
                'avulsos_map' => $remaining_map,
            ];
        }
    }

    /**
     * Calcula as dimensões e peso do pacote único considerando empilhamento tipo "single"
     */
    private static function calculate_single_package_dimensions($combination, $cart_items) {
        $package_items = [];
        $total_weight = 0;
        $total_height = 0;
        $max_width = 0;
        $max_length = 0;

        // Processa grupos
        foreach ($combination['group_items'] as $group_item) {
            $group = $group_item['group'];
            $quantity = $group_item['quantity'];

            // Para grupos com empilhamento "single", calcula dimensões empilhadas
            if ($group['stacking_type'] === 'single') {
                $group_height = $group['height'] + ($group['height_increment'] * ($quantity - 1));
                $group_width = $group['width'] + ($group['width_increment'] * ($quantity - 1));
                $group_length = $group['length'] + ($group['length_increment'] * ($quantity - 1));
            } else {
                // Para outros tipos, usa dimensões base multiplicadas
                $group_height = $group['height'] * $quantity;
                $group_width = $group['width'];
                $group_length = $group['length'];
            }

            $group_weight = $group['weight'] * $quantity;

            $package_items[] = [
                'type' => 'group',
                'group' => $group,
                'quantity' => $quantity,
                'height' => $group_height,
                'width' => $group_width,
                'length' => $group_length,
                'weight' => $group_weight,
            ];

            // Acumula para o pacote total
            $total_weight += $group_weight;
            $total_height += $group_height; // Empilhamento vertical
            $max_width = max($max_width, $group_width);
            $max_length = max($max_length, $group_length);
        }

        // Processa itens individuais
        foreach ($combination['individual_items'] as $product_id => $quantity) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $item_height = $product->get_height() ?: 10;
            $item_width = $product->get_width() ?: 10;
            $item_length = $product->get_length() ?: 10;
            $item_weight = $product->get_weight() ?: 1;

            // Para produtos individuais, empilha verticalmente
            $total_item_height = $item_height * $quantity;
            $total_item_weight = $item_weight * $quantity;

            $package_items[] = [
                'type' => 'individual',
                'product_id' => $product_id,
                'quantity' => $quantity,
                'height' => $total_item_height,
                'width' => $item_width,
                'length' => $item_length,
                'weight' => $total_item_weight,
            ];

            // Acumula para o pacote total
            $total_weight += $total_item_weight;
            $total_height += $total_item_height; // Empilhamento vertical
            $max_width = max($max_width, $item_width);
            $max_length = max($max_length, $item_length);
        }

        // Garante dimensões mínimas
        $total_height = max($total_height, 1);
        $max_width = max($max_width, 1);
        $max_length = max($max_length, 1);
        $total_weight = max($total_weight, 0.1);

        error_log("SPS: Pacote único calculado - Peso: {$total_weight}kg, Dimensões: {$total_height}x{$max_width}x{$max_length}cm");

        return [
            'single_package' => true,
            'items' => $package_items,
            'total_weight' => $total_weight,
            'total_height' => $total_height,
            'total_width' => $max_width,
            'total_length' => $max_length,
            'group_items' => $combination['group_items'],
            'individual_items' => $combination['individual_items'],
        ];
    }
}
