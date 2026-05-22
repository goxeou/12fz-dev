<?php
defined('ABSPATH') || exit;

class TwelveFZ_Permissions {
    private $roles = [
        'manager'        => '门店经理',
        'cashier'        => '收银员',
        'inventory_clerk' => '库存管理员',
        'viewer'         => '仅查看',
    ];

    private $default_caps = [
        'manager' => [
            'manage_products'     => true,
            'manage_orders'       => true,
            'manage_customers'    => true,
            'view_reports'        => true,
            'process_pos'         => true,
            'manage_inventory'    => true,
            'manage_staff'        => true,
            'edit_shop_settings'  => true,
            'view_dashboard'      => true,
        ],
        'cashier' => [
            'manage_products'     => false,
            'manage_orders'       => true,
            'manage_customers'    => false,
            'view_reports'        => false,
            'process_pos'         => true,
            'manage_inventory'    => false,
            'manage_staff'        => false,
            'edit_shop_settings'  => false,
            'view_dashboard'      => true,
        ],
        'inventory_clerk' => [
            'manage_products'     => true,
            'manage_orders'       => false,
            'manage_customers'    => false,
            'view_reports'        => false,
            'process_pos'         => false,
            'manage_inventory'    => true,
            'manage_staff'        => false,
            'edit_shop_settings'  => false,
            'view_dashboard'      => true,
        ],
        'viewer' => [
            'manage_products'     => false,
            'manage_orders'       => false,
            'manage_customers'    => false,
            'view_reports'        => true,
            'process_pos'         => false,
            'manage_inventory'    => false,
            'manage_staff'        => false,
            'edit_shop_settings'  => false,
            'view_dashboard'      => true,
        ],
    ];

    public function init() {
        // 管理菜单
        add_action('dokan_admin_menu', [$this, 'add_store_menu']);
        add_action('admin_menu', [$this, 'add_global_menu']);

        // AJAX
        add_action('wp_ajax_12fz_add_store_staff', [$this, 'ajax_add_staff']);
        add_action('wp_ajax_12fz_remove_store_staff', [$this, 'ajax_remove_staff']);
        add_action('wp_ajax_12fz_update_staff_role', [$this, 'ajax_update_role']);
        add_action('wp_ajax_12fz_get_store_staff', [$this, 'ajax_get_staff']);

        // Dokan Dashboard面板过滤
        add_filter('dokan_get_dashboard_nav', [$this, 'filter_dashboard_nav'], 99);
        add_action('template_redirect', [$this, 'restrict_store_pages'], 99);

        // 门店管理权限
        add_filter('dokan_pre_product_listing_args', [$this, 'filter_product_listing'], 10, 2);
    }

    public function add_store_menu() {
        if (!current_user_can('dokandar')) return;
        $user_id = get_current_user_id();

        // 只有经理可以管理员工
        if ($this->user_can($user_id, 'manage_staff')) {
            add_submenu_page(
                'dokan',
                '员工管理',
                '员工管理',
                'dokandar',
                '12fz-staff',
                [$this, 'render_store_page']
            );
        }
    }

    public function add_global_menu() {
        add_submenu_page(
            'woocommerce',
            '12FZ 门店权限',
            '门店权限',
            'manage_woocommerce',
            '12fz-permissions',
            [$this, 'render_admin_page']
        );
    }

    /**
     * 获取门店员工
     */
    public function ajax_get_staff() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        global $wpdb;
        $store_id = intval($_GET['store_id'] ?? dokan_get_current_user_id());

        $staff = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email 
             FROM {$wpdb->prefix}12fz_store_roles r
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.store_id = %d",
            $store_id
        ));

        wp_send_json_success([
            'staff' => $staff,
            'roles' => $this->roles,
            'caps'  => $this->default_caps,
        ]);
    }

    /**
     * 添加员工
     */
    public function ajax_add_staff() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        $manager_id = dokan_get_current_user_id();
        if (!$this->user_can($manager_id, 'manage_staff')) {
            wp_send_json_error(['message' => '无权限操作']);
        }

        $email   = sanitize_email($_POST['email']);
        $role    = sanitize_text_field($_POST['role']);
        $store_id = intval($_POST['store_id'] ?? $manager_id);

        if (!isset($this->roles[$role])) {
            wp_send_json_error(['message' => '无效角色']);
        }

        // 查找或创建用户
        $user = get_user_by('email', $email);
        if (!$user) {
            $password = wp_generate_password(12);
            $user_id = wp_create_user($email, $password, $email);
            if (is_wp_error($user_id)) {
                wp_send_json_error(['message' => $user_id->get_error_message()]);
            }
            $user = get_user_by('ID', $user_id);
            // 发送欢迎邮件
            wp_new_user_notification($user_id, null, 'user');
        }

        global $wpdb;
        $table = $wpdb->prefix . '12fz_store_roles';

        // 检查是否已存在
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND store_id = %d",
            $user->ID, $store_id
        ));

        $caps = $this->default_caps[$role] ?? [];

        if ($existing) {
            $wpdb->update($table, [
                'role'         => $role,
                'capabilities' => wp_json_encode($caps),
            ], ['id' => $existing]);
        } else {
            $wpdb->insert($table, [
                'user_id'      => $user->ID,
                'store_id'     => $store_id,
                'role'         => $role,
                'capabilities' => wp_json_encode($caps),
            ]);
        }

        // 赋予Dokan卖家角色
        $user->add_role('seller');

        wp_send_json_success([
            'user_id'  => $user->ID,
            'username' => $user->display_name ?: $user->user_login,
            'email'    => $email,
            'role'     => $role,
            'role_name' => $this->roles[$role],
        ]);
    }

    /**
     * 移除员工
     */
    public function ajax_remove_staff() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        $manager_id = dokan_get_current_user_id();
        if (!$this->user_can($manager_id, 'manage_staff')) {
            wp_send_json_error(['message' => '无权限操作']);
        }

        $staff_id = intval($_POST['staff_id']);
        $store_id = intval($_POST['store_id'] ?? $manager_id);

        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . '12fz_store_roles',
            ['id' => $staff_id, 'store_id' => $store_id]
        );

        wp_send_json_success();
    }

    /**
     * 更新角色
     */
    public function ajax_update_role() {
        check_ajax_referer('twelve_fz_nonce', 'nonce');

        $manager_id = dokan_get_current_user_id();
        if (!$this->user_can($manager_id, 'manage_staff')) {
            wp_send_json_error(['message' => '无权限操作']);
        }

        $staff_id = intval($_POST['staff_id']);
        $role     = sanitize_text_field($_POST['role']);
        $store_id = intval($_POST['store_id'] ?? $manager_id);

        if (!isset($this->roles[$role])) {
            wp_send_json_error(['message' => '无效角色']);
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . '12fz_store_roles',
            [
                'role'         => $role,
                'capabilities' => wp_json_encode($this->default_caps[$role]),
            ],
            ['id' => $staff_id, 'store_id' => $store_id]
        );

        wp_send_json_success(['role' => $role, 'role_name' => $this->roles[$role]]);
    }

    /**
     * 过滤Dokan导航菜单
     */
    public function filter_dashboard_nav($nav) {
        $user_id = get_current_user_id();
        if (!$user_id) return $nav;

        // 检查是否是店员（非店主）
        $store_id = dokan_get_current_user_id();
        if ($store_id == $user_id) return $nav; // 店主，不限制

        $caps = $this->get_user_caps($user_id, $store_id);
        if (empty($caps)) return $nav;

        // 根据权限过滤导航项
        $nav_map = [
            'products'       => 'manage_products',
            'orders'         => 'manage_orders',
            'reviews'        => 'manage_products',
            'withdraw'       => 'edit_shop_settings',
            'settings'       => 'edit_shop_settings',
            'reports'        => 'view_reports',
            'dashboard'      => 'view_dashboard',
        ];

        foreach ($nav as $key => $item) {
            if (isset($nav_map[$key]) && empty($caps[$nav_map[$key]])) {
                unset($nav[$key]);
            }
        }

        return $nav;
    }

    /**
     * 限制门店页面访问
     */
    public function restrict_store_pages() {
        if (!function_exists('dokan_is_store_dashboard')) return;
        if (!dokan_is_store_dashboard()) return;
        if (!is_user_logged_in()) return;

        $user_id  = get_current_user_id();
        $store_id = dokan_get_current_user_id();

        // 店主不限制
        if ($store_id == $user_id) return;

        $caps = $this->get_user_caps($user_id, $store_id);
        if (empty($caps)) return;

        global $wp;
        $page_restrictions = [
            'new-product'         => 'manage_products',
            'products'            => 'manage_products',
            'orders'              => 'manage_orders',
            'reports'             => 'view_reports',
            'settings'            => 'edit_shop_settings',
            'withdraw'            => 'edit_shop_settings',
        ];

        $current_endpoint = $wp->query_vars['page'] ?? '';
        foreach ($page_restrictions as $endpoint => $cap) {
            if (strpos($current_endpoint, $endpoint) !== false) {
                if (empty($caps[$cap])) {
                    wp_safe_redirect(dokan_get_navigation_url());
                    exit;
                }
            }
        }
    }

    /**
     * 过滤商品列表
     */
    public function filter_product_listing($args, $store_id) {
        $user_id = get_current_user_id();
        if ($store_id == $user_id) return $args; // 店主

        $caps = $this->get_user_caps($user_id, $store_id);
        if (empty($caps) || empty($caps['manage_products'])) {
            $args['post__in'] = [0]; // 返回空
        }

        return $args;
    }

    // --- 内部方法 ---

    public function user_can($user_id, $capability) {
        $caps = $this->get_user_caps($user_id, dokan_get_current_user_id());
        return !empty($caps[$capability]);
    }

    private function get_user_caps($user_id, $store_id) {
        global $wpdb;
        static $cache = [];

        $key = $user_id . '|' . $store_id;
        if (isset($cache[$key])) return $cache[$key];

        $row = $wpdb->get_var($wpdb->prepare(
            "SELECT capabilities FROM {$wpdb->prefix}12fz_store_roles 
             WHERE user_id = %d AND store_id = %d",
            $user_id, $store_id
        ));

        $cache[$key] = $row ? (array) json_decode($row, true) : [];
        return $cache[$key];
    }

    public function get_roles() {
        return $this->roles;
    }

    public function get_default_caps() {
        return $this->default_caps;
    }

    public function render_admin_page() {
        include TWELVE_FZ_PATH . 'admin/views/permissions-admin.php';
    }

    public function render_store_page() {
        include TWELVE_FZ_PATH . 'admin/views/permissions-store.php';
    }
}
