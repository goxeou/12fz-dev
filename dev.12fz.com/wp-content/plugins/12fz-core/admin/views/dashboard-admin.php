<div class="wrap">
    <h1>12FZ 全局数据看板</h1>
    <div class="notice notice-info">
        <p>所有门店的汇总数据。</p>
    </div>
    <div id="12fz-global-dash">
        <div class="dashboard-widgets" style="display:flex; gap:15px; flex-wrap:wrap;">
            <div class="card" style="flex:1; min-width:200px;">
                <h3>全部门店</h3>
                <p style="font-size:2em; margin:10px 0;">
                    <?php 
                    global $wpdb;
                    $store_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id WHERE um.meta_key = 'wp_capabilities' AND um.meta_value LIKE '%seller%'");
                    echo intval($store_count);
                    ?>
                </p>
            </div>
            <div class="card" style="flex:1; min-width:200px;">
                <h3>全部商品</h3>
                <p style="font-size:2em; margin:10px 0;">
                    <?php echo intval(wp_count_posts('product')->publish ?? 0); ?>
                </p>
            </div>
            <div class="card" style="flex:1; min-width:200px;">
                <h3>全部订单</h3>
                <p style="font-size:2em; margin:10px 0;">
                    <?php 
                    $order_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'");
                    echo intval($order_count);
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>
