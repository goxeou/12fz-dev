<?php
defined('ABSPATH') || exit;

class TwelveFZ_Pricing {
    public function init() {
        // 管理菜单
        add_action('dokan_admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_menu', [$this, 'add_global_admin_menu']);

        // AJAX处理
        add_action('wp_ajax_12fz_save_pricing_rule', [$this, 'ajax_save_rule']);
        add_action('wp_ajax_12fz_delete_pricing_rule', [$this, 'ajax_delete_rule']);
        add_action('wp_ajax_12fz_get_pricing_rules', [$this, 'ajax_get_rules']);

        // 前端价格过滤
        add_filter('woocommerce_product_get_price', [$this, 'apply_dynamic_pricing'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'apply_dynamic_pricing'], 99, 2);
        add_filter('woocommerce_cart_item_price', [$this, 'cart_item_price'], 10, 3);
    }

    /**
     * 添加全局管理菜单（超级管理员用）
     */
    public function add_global_admin_menu() {
        add_submenu_page(
            'woocommerce',
            '12FZ 定价体系',
            '定价体系',
            'manage_woocommerce',
            '12fz-pricing',
            [$this, 'render_admin_page']
        );
    }

    /**
     * 添加Dokan卖家菜单
     */
    public function add_admin_menu() {
        if (!current_user_can('dokandar')) return;
        global $wpdb;
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}12fz_pricing_rules WHERE vendor_id = " . dokan_get_current_user_id());
        $count_badge = $count > 0 ? ' <span class="dokan-badge">' . $count . '</span>' : '';

        add_submenu_page(
            'dokan',
            '定价规则',
            '定价规则' . $count_badge,
            'dokandar',
            '12fz-pricing',
            [$this, 'render_store_page']
        );
    }

    /**
     * 保存定价规则
     */
    public function ajax_save_rule() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        global $wpdb;
        $data = [
            'rule_name'  => sanitize_text_field($_POST['rule_name']),
            'rule_type'  => sanitize_text_field($_POST['rule_type']),
            'priority'   => intval($_POST['priority'] ?? 10),
            'conditions' => wp_json_encode($this->sanitize_conditions($_POST['conditions'] ?? [])),
            'discounts'  => wp_json_encode($this->sanitize_discounts($_POST['discounts'] ?? [])),
            'vendor_id'  => intval($_POST['vendor_id'] ?? dokan_get_current_user_id()),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'end_date'   => sanitize_text_field($_POST['end_date'] ?? ''),
            'status'     => sanitize_text_field($_POST['status'] ?? 'active'),
        ];
        $data['start_date'] = $data['start_date'] ?: null;
        $data['end_date']   = $data['end_date'] ?: null;

        $rule_id = intval($_POST['rule_id'] ?? 0);
        if ($rule_id) {
            $wpdb->update($wpdb->prefix . '12fz_pricing_rules', $data, ['id' => $rule_id]);
        } else {
            $wpdb->insert($wpdb->prefix . '12fz_pricing_rules', $data);
            $rule_id = $wpdb->insert_id;
        }

        wp_send_json_success(['rule_id' => $rule_id]);
    }

    /**
     * 删除规则
     */
    public function ajax_delete_rule() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');
        global $wpdb;
        $rule_id = intval($_POST['rule_id']);
        $vendor_id = dokan_get_current_user_id();

        $wpdb->delete(
            $wpdb->prefix . '12fz_pricing_rules',
            ['id' => $rule_id, 'vendor_id' => $vendor_id]
        );
        wp_send_json_success();
    }

    /**
     * 获取规则列表
     */
    public function ajax_get_rules() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');
        global $wpdb;

        $vendor_id = dokan_get_current_user_id();
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}12fz_pricing_rules WHERE vendor_id = %d ORDER BY priority ASC",
            $vendor_id
        ));

        wp_send_json_success($rules);
    }

    /**
     * 应用动态定价
     */
    public function apply_dynamic_pricing($price, $product) {
        if (!is_shop() && !is_product() && !is_cart() && !is_checkout()) {
            return $price;
        }
        if (is_admin()) return $price;

        global $wpdb;
        $vendor_id = get_post_field('post_author', $product->get_id());

        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}12fz_pricing_rules 
             WHERE (vendor_id = %d OR vendor_id = 0) 
             AND status = 'active'
             AND (start_date IS NULL OR start_date <= NOW())
             AND (end_date IS NULL OR end_date >= NOW())
             ORDER BY priority ASC",
            $vendor_id
        ));

        foreach ($rules as $rule) {
            $conditions = json_decode($rule->conditions, true);
            $discounts  = json_decode($rule->discounts, true);

            if ($this->matches_conditions($conditions, $product)) {
                $price = $this->calculate_discount($price, $discounts);
                break; // 优先级最高的规则生效
            }
        }

        return $price;
    }

    /**
     * 购物车价格展示
     */
    public function cart_item_price($price, $cart_item, $cart_item_key) {
        return $price;
    }

    // --- 内部方法 ---

    private function sanitize_conditions($conditions) {
        $clean = [];
        foreach ($conditions as $cond) {
            $clean[] = [
                'field'    => sanitize_text_field($cond['field'] ?? ''),
                'operator' => sanitize_text_field($cond['operator'] ?? '='),
                'value'    => sanitize_text_field($cond['value'] ?? ''),
            ];
        }
        return $clean;
    }

    private function sanitize_discounts($discounts) {
        $clean = [];
        foreach ($discounts as $disc) {
            $clean[] = [
                'type'  => sanitize_text_field($disc['type'] ?? 'percentage'),
                'value' => floatval($disc['value'] ?? 0),
                'label' => sanitize_text_field($disc['label'] ?? ''),
            ];
        }
        return $clean;
    }

    private function matches_conditions($conditions, $product) {
        if (empty($conditions)) return true;

        foreach ($conditions as $cond) {
            $field    = $cond['field'] ?? '';
            $operator = $cond['operator'] ?? '=';
            $value    = $cond['value'] ?? '';
            $actual   = $this->get_product_field($field, $product);

            switch ($operator) {
                case '=':
                    if ($actual != $value) return false;
                    break;
                case '>':
                    if (!($actual > $value)) return false;
                    break;
                case '<':
                    if (!($actual < $value)) return false;
                    break;
                case '>=':
                    if (!($actual >= $value)) return false;
                    break;
                case '<=':
                    if (!($actual <= $value)) return false;
                    break;
                case 'in':
                    $values = array_map('trim', explode(',', $value));
                    if (!in_array($actual, $values)) return false;
                    break;
                default:
                    return false;
            }
        }

        return true;
    }

    private function get_product_field($field, $product) {
        switch ($field) {
            case 'price':
                return (float) $product->get_price();
            case 'category':
                $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
                return $categories;
            case 'quantity':
                return (int) $product->get_stock_quantity();
            case 'user_role':
                $user = wp_get_current_user();
                return $user->roles;
            default:
                return '';
        }
    }

    private function calculate_discount($price, $discounts) {
        if (empty($discounts)) return $price;

        foreach ($discounts as $disc) {
            switch ($disc['type']) {
                case 'percentage':
                    $price = $price * (1 - $disc['value'] / 100);
                    break;
                case 'fixed':
                    $price = max(0, $price - $disc['value']);
                    break;
                case 'fixed_price':
                    $price = $disc['value'];
                    break;
            }
        }

        return max(1, round($price, 2));
    }

    /**
     * 渲染管理页面（超级管理员）
     */
    public function render_admin_page() {
        include TWELVE_FZ_PATH . 'admin/views/pricing-admin.php';
    }

    /**
     * 渲染卖家页面
     */
    public function render_store_page() {
        include TWELVE_FZ_PATH . 'admin/views/pricing-store.php';
    }
}
