<div class="wrap">
    <h1>12FZ 库存管理（全局）</h1>
    <div class="notice notice-info">
        <p>所有门店库存概览。详细管理请让门店经理在各自的Dokan面板中操作。</p>
    </div>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>门店ID</th>
                <th>商品数</th>
                <th>总库存</th>
                <th>预警数量</th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;
            $stores = $wpdb->get_results(
                "SELECT i.store_id, 
                        COUNT(*) as products,
                        SUM(i.stock_quantity) as total_stock,
                        SUM(CASE WHEN i.stock_quantity <= i.low_stock_threshold AND i.stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                        SUM(CASE WHEN i.stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock
                 FROM {$wpdb->prefix}12fz_inventory i
                 GROUP BY i.store_id"
            );
            if ($stores):
                foreach ($stores as $store):
            ?>
            <tr>
                <td>#<?php echo intval($store->store_id); ?></td>
                <td><?php echo intval($store->products); ?></td>
                <td><?php echo intval($store->total_stock); ?></td>
                <td>
                    <?php if ($store->low_stock > 0): ?>
                        <span style="color:orange;"><?php echo intval($store->low_stock); ?> 库存偏低</span><br>
                    <?php endif; ?>
                    <?php if ($store->out_of_stock > 0): ?>
                        <span style="color:red;"><?php echo intval($store->out_of_stock); ?> 缺货</span>
                    <?php endif; ?>
                    <?php if ($store->low_stock == 0 && $store->out_of_stock == 0): ?>
                        <span style="color:green;">正常</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;">暂无库存数据，请先激活WooCommerce并添加商品。</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
