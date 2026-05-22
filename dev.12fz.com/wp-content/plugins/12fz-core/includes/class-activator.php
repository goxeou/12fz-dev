<?php
defined('ABSPATH') || exit;

class TwelveFZ_Activator {
    public static function activate() {
        self::create_tables();
        self::set_default_options();
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 定价规则表
        $sql_pricing = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}12fz_pricing_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rule_name VARCHAR(255) NOT NULL,
            rule_type VARCHAR(50) NOT NULL COMMENT 'tiered|volume|store_specific|role_based',
            priority INT DEFAULT 10,
            conditions TEXT COMMENT 'JSON条件',
            discounts TEXT COMMENT 'JSON折扣规则',
            vendor_id BIGINT UNSIGNED DEFAULT 0,
            start_date DATETIME NULL,
            end_date DATETIME NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type (rule_type),
            INDEX idx_vendor (vendor_id),
            INDEX idx_status (status)
        ) $charset_collate;";

        // SKU库存表
        $sql_inventory = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}12fz_inventory (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED DEFAULT 0,
            sku VARCHAR(100) NOT NULL,
            store_id BIGINT UNSIGNED NOT NULL COMMENT 'Dokan vendor/store ID',
            stock_quantity INT DEFAULT 0,
            low_stock_threshold INT DEFAULT 5,
            warehouse_location VARCHAR(255) DEFAULT '',
            last_restocked DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_product (product_id),
            INDEX idx_store (store_id),
            INDEX idx_sku (sku)
        ) $charset_collate;";

        // 门店权限表
        $sql_permissions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}12fz_store_roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            store_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(50) NOT NULL COMMENT 'manager|cashier|inventory_clerk|viewer',
            capabilities TEXT COMMENT 'JSON权限列表',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_user_store (user_id, store_id),
            INDEX idx_store (store_id),
            INDEX idx_role (role)
        ) $charset_collate;";

        // 数据看板缓存表
        $sql_dashboard = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}12fz_dashboard_cache (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            store_id BIGINT UNSIGNED NOT NULL,
            metric_key VARCHAR(100) NOT NULL,
            metric_value TEXT,
            period VARCHAR(20) NOT NULL COMMENT 'daily|weekly|monthly',
            recorded_at DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_metric (store_id, metric_key, period, recorded_at)
        ) $charset_collate;";

        // 库存预警日志
        $sql_alerts = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}12fz_stock_alerts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            store_id BIGINT UNSIGNED NOT NULL,
            alert_type VARCHAR(50) NOT NULL COMMENT 'low_stock|out_of_stock|overstock',
            current_stock INT DEFAULT 0,
            threshold INT DEFAULT 0,
            notified TINYINT(1) DEFAULT 0,
            resolved TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_store (store_id),
            INDEX idx_resolved (resolved)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_pricing);
        dbDelta($sql_inventory);
        dbDelta($sql_permissions);
        dbDelta($sql_dashboard);
        dbDelta($sql_alerts);
    }

    private static function set_default_options() {
        add_option('12fz_low_stock_threshold', 5);
        add_option('12fz_auto_alert', 'yes');
        add_option('12fz_dashboard_refresh_interval', 3600);
    }
}
