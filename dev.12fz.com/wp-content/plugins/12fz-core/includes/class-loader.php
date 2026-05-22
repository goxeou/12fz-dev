<?php
defined('ABSPATH') || exit;

class TwelveFZ_Loader {
    private $modules = [];

    public function init() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies() {
        // 定价体系
        require_once TWELVE_FZ_PATH . 'includes/class-pricing.php';
        $this->modules['pricing'] = new TwelveFZ_Pricing();

        // SKU库存管理
        require_once TWELVE_FZ_PATH . 'includes/class-inventory.php';
        $this->modules['inventory'] = new TwelveFZ_Inventory();

        // 门店权限控制
        require_once TWELVE_FZ_PATH . 'includes/class-permissions.php';
        $this->modules['permissions'] = new TwelveFZ_Permissions();

        // 数据看板
        require_once TWELVE_FZ_PATH . 'includes/class-dashboard.php';
        $this->modules['dashboard'] = new TwelveFZ_Dashboard();
    }

    private function register_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);

        // 初始化各模块
        foreach ($this->modules as $module) {
            if (method_exists($module, 'init')) {
                $module->init();
            }
        }
    }

    public function enqueue_admin_assets($hook) {
        wp_enqueue_style('12fz-admin', TWELVE_FZ_URL . 'admin/css/admin.css', [], TWELVE_FZ_VERSION);
        wp_enqueue_script('12fz-admin', TWELVE_FZ_URL . 'admin/js/admin.js', ['jquery'], TWELVE_FZ_VERSION, true);
        wp_localize_script('12fz-admin', 'twelveFzData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('twelve_fz_nonce'),
        ]);
    }

    public function enqueue_public_assets() {
        if (function_exists('dokan_is_store_page') && dokan_is_store_page()) {
            wp_enqueue_style('12fz-store', TWELVE_FZ_URL . 'public/css/store.css', [], TWELVE_FZ_VERSION);
            wp_enqueue_script('12fz-store', TWELVE_FZ_URL . 'public/js/store.js', ['jquery'], TWELVE_FZ_VERSION, true);
        }
    }
}
