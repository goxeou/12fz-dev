<div class="dokan-dashboard-wrap">
    <?php dokan_get_template('dashboard-nav.php', ['active_menu' => '12fz-inventory']); ?>

    <div class="dokan-dashboard-content">
        <header class="dokan-dashboard-header">
            <h1 class="entry-title"><?php esc_html_e('库存管理', '12fz-core'); ?></h1>
        </header>

        <div class="dokan-form-group">
            <input type="text" id="12fz-inventory-search" class="dokan-form-control" placeholder="搜索商品名称或SKU..." style="max-width:300px; display:inline-block;">
            <button class="dokan-btn dokan-btn-theme" id="12fz-inventory-refresh"><i class="fa fa-refresh"></i> 刷新</button>
            <button class="dokan-btn dokan-btn-success" id="12fz-bulk-save"><i class="fa fa-save"></i> 批量保存</button>
        </div>

        <div id="12fz-alerts-panel" style="display:none; margin-bottom:15px;">
            <div class="alert alert-danger"></div>
        </div>

        <table class="dokan-table dokan-table-striped" id="12fz-inventory-table">
            <thead>
                <tr>
                    <th style="width:30px"><input type="checkbox" id="select-all"></th>
                    <th>商品</th>
                    <th>SKU</th>
                    <th>库存数量</th>
                    <th>低库存阈值</th>
                    <th>最后补货</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody id="12fz-inventory-body">
                <tr><td colspan="7" style="text-align:center">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    var inventoryData = [];

    function loadInventory() {
        var search = $('#12fz-inventory-search').val();
        $('#12fz-inventory-body').html('<tr><td colspan="7" style="text-align:center"><i class="fa fa-spinner fa-spin"></i> 加载中...</td></tr>');

        $.get(ajaxurl, {
            action: '12fz_get_inventory',
            nonce: twelveFzData.nonce,
            search: search
        }, function(res) {
            if (!res.success) return;
            inventoryData = res.data;
            renderTable(inventoryData);
        });
    }

    function renderTable(data) {
        var html = '';
        if (!data || data.length === 0) {
            html = '<tr><td colspan="7" style="text-align:center">暂无库存数据</td></tr>';
        } else {
            $.each(data, function(i, item) {
                var statusClass = 'dokan-label-success';
                var statusText = '正常';
                if (item.stock_quantity <= 0) {
                    statusClass = 'dokan-label-danger';
                    statusText = '缺货';
                } else if (item.stock_quantity <= item.low_stock_threshold) {
                    statusClass = 'dokan-label-warning';
                    statusText = '偏低';
                }
                html += '<tr>' +
                    '<td><input type="checkbox" class="inventory-checkbox" value="' + item.id + '"></td>' +
                    '<td>' + (item.product_name || 'ID:' + item.product_id) + '</td>' +
                    '<td>' + (item.sku || '-') + '</td>' +
                    '<td><input type="number" class="dokan-form-control input-sm stock-qty" data-id="' + item.id + '" data-product="' + item.product_id + '" value="' + item.stock_quantity + '" style="width:80px" min="0"></td>' +
                    '<td><input type="number" class="dokan-form-control input-sm stock-threshold" data-id="' + item.id + '" value="' + item.low_stock_threshold + '" style="width:80px" min="1"></td>' +
                    '<td>' + (item.last_restocked || '-') + '</td>' +
                    '<td><span class="dokan-label ' + statusClass + '">' + statusText + '</span></td>' +
                '</tr>';
            });
        }
        $('#12fz-inventory-body').html(html);
    }

    $('#12fz-inventory-search').on('keyup', function(e) {
        if (e.which === 13) loadInventory();
    });

    $('#12fz-inventory-refresh').click(loadInventory);

    $('#select-all').change(function() {
        $('.inventory-checkbox').prop('checked', $(this).is(':checked'));
    });

    $('#12fz-bulk-save').click(function() {
        var items = [];
        $('.stock-qty').each(function() {
            var $row = $(this).closest('tr');
            items.push({
                product_id: $(this).data('product'),
                quantity: parseInt($(this).val()) || 0,
                low_stock_threshold: parseInt($row.find('.stock-threshold').val()) || 5
            });
        });

        if (items.length === 0) return;

        $.post(ajaxurl, {
            action: '12fz_bulk_stock_update',
            nonce: twelveFzData.nonce,
            items: JSON.stringify(items)
        }, function(res) {
            if (res.success) {
                dokan_sweetalert('保存成功，共更新 ' + res.data.updated + ' 项', {icon:'success'});
                loadInventory();
            } else {
                dokan_sweetalert('保存失败', {icon:'error'});
            }
        });
    });

    loadInventory();
    setInterval(loadInventory, 60000); // 自动刷新
});
</script>
