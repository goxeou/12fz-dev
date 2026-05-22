<div class="wrap">
    <h1>12FZ 门店权限管理</h1>
    <div class="notice notice-info">
        <p>全局门店角色与权限配置。可在 WP后台 > 用户 中管理门店员工。</p>
    </div>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>角色名称</th>
                <th>管理商品</th>
                <th>管理订单</th>
                <th>POS收银</th>
                <th>管理库存</th>
                <th>查看报表</th>
                <th>管理员工</th>
                <th>设置门店</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $perm = new TwelveFZ_Permissions();
            $roles = $perm->get_roles();
            $caps  = $perm->get_default_caps();
            foreach ($roles as $key => $label): 
                $role_caps = $caps[$key] ?? [];
            ?>
            <tr>
                <td><strong><?php echo esc_html($label); ?></strong></td>
                <td><?php echo !empty($role_caps['manage_products']) ? '✅' : '—'; ?></td>
                <td><?php echo !empty($role_caps['manage_orders']) ? '✅' : '—'; ?></td>
                <td><?php echo !empty($role_caps['process_pos']) ? '✅' : '—'; ?></td>
                <td><?php echo !empty($role_caps['manage_inventory']) ? '✅' : '—'; ?></td>
                <td><?php echo !empty($role_caps['view_reports']) ? '✅' : '—'; ?></td>
                <td><?php echo !empty($role_caps['manage_staff']) ? '✅' : '—'; ?></td>
                <td><?php echo !empty($role_caps['edit_shop_settings']) ? '✅' : '—'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
