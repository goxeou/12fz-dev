<?php
/**
 * Plugin Name: 12FZ Core
 * Plugin URI:  https://12fz.com
 * Description: 12FZ非遗平台电商核心模块 - 定价体系、SKU库存、门店权限、数据看板
 * Version:     1.0.0
 * Author:      12FZ Dev Team
 * Text Domain: 12fz-core
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce, dokan-lite
 */

defined('ABSPATH') || exit;

define('TWELVE_FZ_VERSION', '1.0.0');
define('TWELVE_FZ_FILE', __FILE__);
define('TWELVE_FZ_PATH', plugin_dir_path(__FILE__));
define('TWELVE_FZ_URL', plugin_dir_url(__FILE__));

// 核心加载器
require_once TWELVE_FZ_PATH . 'includes/class-loader.php';

// 初始化
function twelve_fz_init() {
    // 检查依赖
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>12FZ Core 需要先安装并激活 WooCommerce。</p></div>';
        });
        return;
    }

    $plugin = new TwelveFZ_Loader();
    $plugin->init();
}
add_action('plugins_loaded', 'twelve_fz_init');

// 激活钩子
register_activation_hook(__FILE__, 'twelve_fz_activate');
function twelve_fz_activate() {
    require_once TWELVE_FZ_PATH . 'includes/class-activator.php';
    TwelveFZ_Activator::activate();
}
