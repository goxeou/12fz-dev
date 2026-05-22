<?php
defined('ABSPATH') || exit;

class TwelveFZ_Dashboard {
    public function init() {
        // 管理菜单
        add_action('dokan_admin_menu', [$this, 'add_store_menu']);
        add_action('admin_menu', [$this, 'add_global_menu']);

        // AJAX
        add_action('wp_ajax_12fz_get_dashboard_data', [$this, 'ajax_get_data']);
        add_action('wp_ajax_12fz_refresh_dashboard', [$this, 'ajax_refresh_cache']);
        add_action('wp_ajax_12fz_export_report', [$this, 'ajax_export_report']);

        // 定时刷新缓存
        add_action('12fz_dashboard_refresh', [$this, 'refresh_all_caches']);
        if (!wp_next_scheduled('12fz_dashboard_refresh')) {
            wp_schedule_event(time(), 'hourly', '12fz_dashboard_refresh');
        }
    }

    /**
     * 卖家数据看板菜单
     */
    public function add_store_menu() {
        if (!current_user_can('dokandar')) return;

        global $wpdb;
        $alerts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}12fz_stock_alerts WHERE store_id = %d AND resolved = 0",
            dokan_get_current_user_id()
        ));
        $badge = $alerts > 0 ? ' <span class="dokan-badge dokan-badge-danger">' . $alerts . '</span>' : '';

        add_submenu_page(
            'dokan',
            '数据看板',
            '数据看板' . $badge,
            'dokandar',
            '12fz-dashboard',
            [$this, 'render_store_page']
        );
    }

    /**
     * 全局看板菜单
     */
    public function add_global_menu() {
        add_submenu_page(
            'woocommerce',
            '12FZ 数据看板',
            '数据看板',
            'manage_woocommerce',
            '12fz-dashboard-global',
            [$this, 'render_admin_page']
        );
    }

    /**
     * AJAX: 获取看板数据
     */
    public function ajax_get_data() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        $store_id = intval($_GET['store_id'] ?? dokan_get_current_user_id());
        $period   = sanitize_text_field($_GET['period'] ?? 'monthly');
        $force    = sanitize_text_field($_GET['force'] ?? '') === '1';

        $data = $this->get_dashboard_data($store_id, $period, $force);
        wp_send_json_success($data);
    }

    /**
     * 强制刷新缓存
     */
    public function ajax_refresh_cache() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');
        $store_id = intval($_POST['store_id'] ?? dokan_get_current_user_id());
        $this->compute_and_cache($store_id, 'daily');
        $this->compute_and_cache($store_id, 'weekly');
        $this->compute_and_cache($store_id, 'monthly');
        wp_send_json_success(['message' => '缓存已刷新']);
    }

    /**
     * 导出报表
     */
    public function ajax_export_report() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        $store_id = intval($_POST['store_id'] ?? dokan_get_current_user_id());
        $period   = sanitize_text_field($_POST['period'] ?? 'monthly');
        $type     = sanitize_text_field($_POST['type'] ?? 'sales');

        $data = $this->get_report_data($store_id, $period, $type);

        // CSV输出
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="report-' . $type . '-' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');

        // 表头
        fputcsv($output, array_keys($data[0] ?? ['日期', '金额', '订单数', '商品数']));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    // --- 数据计算 ---

    /**
     * 获取看板数据（优先使用缓存）
     */
    public function get_dashboard_data($store_id, $period = 'monthly', $force = false) {
        global $wpdb;
        $cache_table = $wpdb->prefix . '12fz_dashboard_cache';
        $refresh_interval = intval(get_option('12fz_dashboard_refresh_interval', 3600));

        // 检查缓存是否过期
        if (!$force) {
            $cached = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $cache_table WHERE store_id = %d AND period = %s AND recorded_at = CURDATE()",
                $store_id, $period
            ));

            if (!empty($cached)) {
                $result = [];
                foreach ($cached as $row) {
                    $result[$row->metric_key] = json_decode($row->metric_value, true);
                }
                return $result;
            }
        }

        return $this->compute_and_cache($store_id, $period);
    }

    /**
     * 计算并缓存数据
     */
    private function compute_and_cache($store_id, $period) {
        global $wpdb;
        $cache_table = $wpdb->prefix . '12fz_dashboard_cache';
        $data = [];

        // 销售数据
        $data['sales'] = $this->compute_sales($store_id, $period);

        // 订单统计
        $data['orders'] = $this->compute_orders($store_id, $period);

        // 商品统计
        $data['products'] = $this->compute_products($store_id);

        // 库存预警
        $data['alerts'] = $this->compute_alerts($store_id);

        // 门店概览
        $data['store_overview'] = $this->compute_store_overview($store_id);

        // 写入缓存
        foreach ($data as $key => $value) {
            $wpdb->replace($cache_table, [
                'store_id'      => $store_id,
                'metric_key'    => $key,
                'metric_value'  => wp_json_encode($value),
                'period'        => $period,
                'recorded_at'   => current_time('Y-m-d'),
            ]);
        }

        return $data;
    }

    /**
     * 计算销售额
     */
    private function compute_sales($store_id, $period) {
        global $wpdb;

        $date_condition = $this->date_condition($period);
        $results = [];

        // 按天汇总
        $daily = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(p.post_date) as date, 
                    COUNT(DISTINCT p.ID) as orders,
                    SUM(oim.meta_value) as total_sales
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
             WHERE p.post_type = 'shop_order'
               AND p.post_status IN ('wc-completed', 'wc-processing')
               AND p.post_author = %d
               AND oim.meta_key = '_line_total'
               $date_condition
             GROUP BY DATE(p.post_date)
             ORDER BY date ASC",
            $store_id
        ));

        $total_sales = 0;
        $total_orders = 0;
        foreach ($daily as $row) {
            $total_sales += floatval($row->total_sales);
            $total_orders += intval($row->orders);
        }

        return [
            'total_sales'    => round($total_sales, 2),
            'total_orders'   => $total_orders,
            'average_order'  => $total_orders > 0 ? round($total_sales / $total_orders, 2) : 0,
            'daily'          => $daily,
        ];
    }

    /**
     * 计算订单统计
     */
    private function compute_orders($store_id, $period) {
        global $wpdb;
        $date_condition = $this->date_condition($period);

        $status_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_status, COUNT(*) as count
             FROM {$wpdb->posts}
             WHERE post_type = 'shop_order'
               AND post_author = %d
               $date_condition
             GROUP BY post_status",
            $store_id
        ));

        $summary = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'cancelled' => 0, 'refunded' => 0, 'on-hold' => 0];
        foreach ($status_counts as $row) {
            $status = str_replace('wc-', '', $row->post_status);
            $summary[$status] = intval($row->count);
        }

        return [
            'by_status' => $summary,
            'total'     => array_sum($summary),
        ];
    }

    /**
     * 商品统计
     */
    private function compute_products($store_id) {
        global $wpdb;

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'product' AND post_status = 'publish'",
            $store_id
        ));

        $low_stock = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}12fz_inventory 
             WHERE store_id = %d AND stock_quantity > 0 AND stock_quantity <= low_stock_threshold",
            $store_id
        ));

        $out_of_stock = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}12fz_inventory WHERE store_id = %d AND stock_quantity <= 0",
            $store_id
        ));

        return [
            'total_products' => intval($total),
            'low_stock'      => intval($low_stock),
            'out_of_stock'   => intval($out_of_stock),
            'in_stock'       => intval($total) - intval($low_stock) - intval($out_of_stock),
        ];
    }

    /**
     * 预警统计
     */
    private function compute_alerts($store_id) {
        global $wpdb;

        $active = $wpdb->get_results($wpdb->prepare(
            "SELECT alert_type, COUNT(*) as count 
             FROM {$wpdb->prefix}12fz_stock_alerts 
             WHERE store_id = %d AND resolved = 0
             GROUP BY alert_type",
            $store_id
        ));

        $result = ['low_stock' => 0, 'out_of_stock' => 0, 'overstock' => 0];
        foreach ($active as $row) {
            $result[$row->alert_type] = intval($row->count);
        }

        return $result;
    }

    /**
     * 门店概览
     */
    private function compute_store_overview($store_id) {
        $vendor = dokan()->vendor->get($store_id);
        if (!$vendor) return [];

        return [
            'store_name'    => $vendor->get_shop_name(),
            'store_url'     => $vendor->get_shop_url(),
            'rating'        => $vendor->get_rating(),
            'since'         => $vendor->get_join_date(),
            'products_count' => count($vendor->get_products_ids()),
        ];
    }

    /**
     * 导出明细数据
     */
    private function get_report_data($store_id, $period, $type) {
        global $wpdb;
        $date_condition = $this->date_condition($period);

        switch ($type) {
            case 'sales':
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(p.post_date) as date,
                            p.ID as order_id,
                            p.post_status as status,
                            oim.meta_value as total
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
                     INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                     WHERE p.post_type = 'shop_order'
                       AND p.post_author = %d
                       AND oim.meta_key = '_line_total'
                       $date_condition
                     ORDER BY p.post_date DESC
                     LIMIT 500",
                    $store_id
                ), ARRAY_A);

            case 'products':
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT p.ID, p.post_title, i.sku, i.stock_quantity, i.low_stock_threshold
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->prefix}12fz_inventory i ON p.ID = i.product_id AND i.store_id = %d
                     WHERE p.post_author = %d AND p.post_type = 'product'
                     ORDER BY p.post_title",
                    $store_id, $store_id
                ), ARRAY_A);

            default:
                return [];
        }
    }

    /**
     * 时间范围条件
     */
    private function date_condition($period) {
        switch ($period) {
            case 'daily':
                return "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'weekly':
                return "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'monthly':
                return "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'yearly':
                return "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }

    /**
     * 刷新所有门店缓存
     */
    public function refresh_all_caches() {
        global $wpdb;
        $stores = $wpdb->get_col("SELECT DISTINCT store_id FROM {$wpdb->prefix}12fz_inventory");

        foreach ($stores as $store_id) {
            $this->compute_and_cache($store_id, 'daily');
            $this->compute_and_cache($store_id, 'weekly');
            $this->compute_and_cache($store_id, 'monthly');
        }
    }

    public function render_admin_page() {
        include TWELVE_FZ_PATH . 'admin/views/dashboard-admin.php';
    }

    public function render_store_page() {
        include TWELVE_FZ_PATH . 'admin/views/dashboard-store.php';
    }
}
