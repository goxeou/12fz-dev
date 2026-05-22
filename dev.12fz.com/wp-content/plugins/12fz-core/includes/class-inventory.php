<?php
defined('ABSPATH') || exit;

class TwelveFZ_Inventory {
    public function init() {
        // 管理菜单
        add_action('dokan_admin_menu', [$this, 'add_store_menu']);
        add_action('admin_menu', [$this, 'add_global_menu']);

        // AJAX
        add_action('wp_ajax_12fz_update_stock', [$this, 'ajax_update_stock']);
        add_action('wp_ajax_12fz_bulk_stock_update', [$this, 'ajax_bulk_stock_update']);
        add_action('wp_ajax_12fz_get_inventory', [$this, 'ajax_get_inventory']);
        add_action('wp_ajax_12fz_get_stock_alerts', [$this, 'ajax_get_stock_alerts']);

        // 库存挂钩
        add_action('woocommerce_product_set_stock', [$this, 'sync_stock'], 10, 1);
        add_action('woocommerce_variation_set_stock', [$this, 'sync_stock'], 10, 1);
        add_action('woocommerce_low_stock_notification', [$this, 'create_alert'], 10, 2);
        add_action('woocommerce_no_stock_notification', [$this, 'create_out_of_stock_alert'], 10, 2);

        // AI预警调度（半小时检查一次）
        add_action('12fz_stock_alert_check', [$this, 'check_all_stores_alerts']);
        if (!wp_next_scheduled('12fz_stock_alert_check')) {
            wp_schedule_event(time(), 'hourly', '12fz_stock_alert_check');
        }
    }

    /**
     * 卖家库存管理菜单
     */
    public function add_store_menu() {
        if (!current_user_can('dokandar')) return;
        add_submenu_page(
            'dokan',
            '库存管理',
            '库存管理',
            'dokandar',
            '12fz-inventory',
            [$this, 'render_store_page']
        );
    }

    /**
     * 全局库存菜单
     */
    public function add_global_menu() {
        add_submenu_page(
            'woocommerce',
            '12FZ 库存管理',
            '库存管理',
            'manage_woocommerce',
            '12fz-inventory-global',
            [$this, 'render_admin_page']
        );
    }

    /**
     * AJAX: 更新单个商品库存
     */
    public function ajax_update_stock() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        $product_id    = intval($_POST['product_id']);
        $store_id      = intval($_POST['store_id'] ?? dokan_get_current_user_id());
        $quantity      = intval($_POST['quantity']);
        $low_stock     = intval($_POST['low_stock_threshold'] ?? get_option('12fz_low_stock_threshold', 5));

        global $wpdb;
        $table = $wpdb->prefix . '12fz_inventory';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE product_id = %d AND store_id = %d",
            $product_id, $store_id
        ));

        $data = [
            'product_id'         => $product_id,
            'sku'                => get_post_meta($product_id, '_sku', true) ?: '',
            'store_id'           => $store_id,
            'stock_quantity'     => $quantity,
            'low_stock_threshold' => $low_stock,
            'last_restocked'     => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($table, $data, ['id' => $existing]);
            $data['id'] = $existing;
        } else {
            $wpdb->insert($table, $data);
            $data['id'] = $wpdb->insert_id;
        }

        // 同步WooCommerce库存
        $product = wc_get_product($product_id);
        if ($product) {
            $product->set_stock_quantity($quantity);
            $product->save();
        }

        $this->check_stock_alert($product_id, $store_id, $quantity, $low_stock);
        wp_send_json_success($data);
    }

    /**
     * AJAX: 批量更新库存
     */
    public function ajax_bulk_stock_update() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        $items = json_decode(stripslashes($_POST['items']), true);
        if (empty($items)) {
            wp_send_json_error(['message' => '无更新数据']);
        }

        global $wpdb;
        $table = $wpdb->prefix . '12fz_inventory';
        $updated = 0;

        foreach ($items as $item) {
            $product_id = intval($item['product_id']);
            $store_id   = intval($item['store_id'] ?? dokan_get_current_user_id());
            $quantity   = intval($item['quantity']);

            if (!$product_id) continue;

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE product_id = %d AND store_id = %d",
                $product_id, $store_id
            ));

            $data = [
                'stock_quantity'    => $quantity,
                'low_stock_threshold' => intval($item['low_stock_threshold'] ?? get_option('12fz_low_stock_threshold', 5)),
                'last_restocked'    => current_time('mysql'),
            ];

            if ($existing) {
                $wpdb->update($table, $data, ['id' => $existing]);
            } else {
                $data['product_id'] = $product_id;
                $data['store_id']   = $store_id;
                $data['sku']        = get_post_meta($product_id, '_sku', true) ?: '';
                $wpdb->insert($table, $data);
            }

            // 同步WooCommerce
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_stock_quantity($quantity);
                $product->save();
            }

            $this->check_stock_alert($product_id, $store_id, $quantity, $data['low_stock_threshold']);
            $updated++;
        }

        wp_send_json_success(['updated' => $updated]);
    }

    /**
     * AJAX: 获取库存数据
     */
    public function ajax_get_inventory() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        global $wpdb;
        $store_id = intval($_GET['store_id'] ?? dokan_get_current_user_id());
        $search   = sanitize_text_field($_GET['search'] ?? '');

        $where = "i.store_id = %d";
        $params = [$store_id];

        if ($search) {
            $where .= " AND (i.sku LIKE %s OR p.post_title LIKE %s)";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, p.post_title as product_name, p.post_status 
             FROM {$wpdb->prefix}12fz_inventory i
             LEFT JOIN {$wpdb->posts} p ON i.product_id = p.ID
             WHERE $where
             ORDER BY i.updated_at DESC
             LIMIT 100",
            $params
        ));

        wp_send_json_success($results);
    }

    /**
     * 获取库存预警
     */
    public function ajax_get_stock_alerts() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        global $wpdb;
        $store_id = intval($_GET['store_id'] ?? dokan_get_current_user_id());

        $alerts = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, p.post_title as product_name 
             FROM {$wpdb->prefix}12fz_stock_alerts a
             LEFT JOIN {$wpdb->posts} p ON a.product_id = p.ID
             WHERE a.store_id = %d AND a.resolved = 0
             ORDER BY a.created_at DESC",
            $store_id
        ));

        wp_send_json_success($alerts);
    }

    /**
     * 同步WooCommerce库存变更到自定义表
     */
    public function sync_stock($product) {
        global $wpdb;
        $table = $wpdb->prefix . '12fz_inventory';
        $product_id = $product->get_id();
        $quantity   = $product->get_stock_quantity();

        // 获取所有关联门店的库存
        $store_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT store_id FROM $table WHERE product_id = %d",
            $product_id
        ));

        if (empty($store_ids)) {
            // 无记录则创建一条（归属商品作者的门店）
            $author_id = get_post_field('post_author', $product_id);
            $store_ids = [$author_id];
        }

        foreach ($store_ids as $store_id) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE product_id = %d AND store_id = %d",
                $product_id, $store_id
            ));

            $data = [
                'stock_quantity'    => $quantity,
                'low_stock_threshold' => get_option('12fz_low_stock_threshold', 5),
                'last_restocked'    => current_time('mysql'),
            ];

            if ($existing) {
                $wpdb->update($table, $data, ['id' => $existing]);
            } else {
                $data['product_id'] = $product_id;
                $data['store_id']   = $store_id;
                $data['sku']        = $product->get_sku() ?: '';
                $wpdb->insert($table, $data);
            }
        }
    }

    /**
     * 低库存预警
     */
    public function create_alert($product_id, $quantity) {
        $author_id = get_post_field('post_author', $product_id);
        $this->check_stock_alert($product_id, $author_id, $quantity, get_option('12fz_low_stock_threshold', 5));
    }

    public function create_out_of_stock_alert($product_id) {
        $author_id = get_post_field('post_author', $product_id);
        $this->check_stock_alert($product_id, $author_id, 0, 0);
    }

    /**
     * AI预警: 检查所有门店库存
     */
    public function check_all_stores_alerts() {
        global $wpdb;
        $table = $wpdb->prefix . '12fz_inventory';
        $threshold = get_option('12fz_low_stock_threshold', 5);

        $low_stock_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE stock_quantity <= low_stock_threshold AND stock_quantity > 0"
        ));

        foreach ($low_stock_items as $item) {
            $this->check_stock_alert($item->product_id, $item->store_id, $item->stock_quantity, $item->low_stock_threshold);
        }

        $out_of_stock = $wpdb->get_results(
            "SELECT * FROM $table WHERE stock_quantity <= 0"
        );

        foreach ($out_of_stock as $item) {
            $this->check_stock_alert($item->product_id, $item->store_id, 0, 0);
        }
    }

    // --- 内部方法 ---

    private function check_stock_alert($product_id, $store_id, $quantity, $threshold) {
        global $wpdb;
        $table = $wpdb->prefix . '12fz_stock_alerts';

        // 检查已有未解决的预警
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE product_id = %d AND store_id = %d AND resolved = 0",
            $product_id, $store_id
        ));

        if ($quantity <= 0) {
            // 缺货预警
            if (!$existing) {
                $wpdb->insert($table, [
                    'product_id'    => $product_id,
                    'store_id'      => $store_id,
                    'alert_type'    => 'out_of_stock',
                    'current_stock' => 0,
                    'threshold'     => $threshold,
                ]);
            }
        } elseif ($quantity <= $threshold) {
            // 低库存预警
            if (!$existing) {
                $wpdb->insert($table, [
                    'product_id'    => $product_id,
                    'store_id'      => $store_id,
                    'alert_type'    => 'low_stock',
                    'current_stock' => $quantity,
                    'threshold'     => $threshold,
                ]);
            }
        } else {
            // 库存恢复正常，解除预警
            if ($existing) {
                $wpdb->update($table, ['resolved' => 1], ['id' => $existing]);
            }
        }
    }

    public function render_admin_page() {
        include TWELVE_FZ_PATH . 'admin/views/inventory-admin.php';
    }

    public function render_store_page() {
        include TWELVE_FZ_PATH . 'admin/views/inventory-store.php';
    }
}
