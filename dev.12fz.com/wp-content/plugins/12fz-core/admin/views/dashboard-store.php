<div class="dokan-dashboard-wrap">
    <?php dokan_get_template('dashboard-nav.php', ['active_menu' => '12fz-dashboard']); ?>

    <div class="dokan-dashboard-content">
        <header class="dokan-dashboard-header">
            <h1 class="entry-title">
                <?php esc_html_e('数据看板', '12fz-core'); ?>
                <button class="dokan-btn dokan-btn-sm dokan-right" id="12fz-dash-refresh">
                    <i class="fa fa-refresh"></i> 刷新数据
                </button>
            </h1>
        </header>

        <!-- 概览卡片 -->
        <div class="dokan-dash-widgets" id="12fz-dash-overview">
            <div class="dokan-widget-loader"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
        </div>

        <div class="dokan-clearfix" style="margin-top:20px;">
            <!-- 预警面板 -->
            <div class="dokan-left" style="width:48%;">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>库存预警</strong></div>
                    <div class="panel-body" id="12fz-alerts-list">加载中...</div>
                </div>
            </div>

            <!-- 筛选 -->
            <div class="dokan-right" style="width:48%;">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <strong>快速操作</strong>
                    </div>
                    <div class="panel-body" style="text-align:center;">
                        <a href="<?php echo dokan_get_navigation_url('12fz-inventory'); ?>" class="dokan-btn dokan-btn-theme">管理库存</a>
                        <a href="<?php echo dokan_get_navigation_url('12fz-pricing'); ?>" class="dokan-btn dokan-btn-theme">定价规则</a>
                        <a href="<?php echo dokan_get_navigation_url('12fz-staff'); ?>" class="dokan-btn dokan-btn-default">员工管理</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- 销售趋势 -->
        <div class="dokan-clearfix" style="margin-top:20px;">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>销售趋势 (近30天)</strong>
                    <select id="12fz-dash-period" class="dokan-form-control" style="width:auto; float:right;">
                        <option value="daily">今日</option>
                        <option value="weekly">本周</option>
                        <option value="monthly" selected>本月</option>
                        <option value="yearly">今年</option>
                    </select>
                </div>
                <div class="panel-body" id="12fz-sales-chart">
                    <div class="dokan-widget-loader"><i class="fa fa-spinner fa-spin"></i> 加载中...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var currentPeriod = 'monthly';

    function loadDashboard(period) {
        period = period || currentPeriod;
        currentPeriod = period;

        $.get(ajaxurl, {
            action: '12fz_get_dashboard_data',
            nonce: twelveFzData.nonce,
            period: period
        }, function(res) {
            if (!res.success) return;
            renderOverview(res.data);
            renderAlerts(res.data.alerts);
            renderSalesChart(res.data.sales);
        });
    }

    function renderOverview(data) {
        var sales = data.sales || {};
        var products = data.products || {};
        var orders = data.orders || {};
        var alerts = data.alerts || {};
        var totalAlerts = (alerts.low_stock || 0) + (alerts.out_of_stock || 0);

        var html = 
            '<div class="dokan-dash-single" style="background:#e8f5e9;">' +
                '<h3>¥' + (sales.total_sales || '0').toLocaleString() + '</h3><p>总销售额</p>' +
            '</div>' +
            '<div class="dokan-dash-single" style="background:#e3f2fd;">' +
                '<h3>' + (orders.total || 0) + '</h3><p>总订单</p>' +
            '</div>' +
            '<div class="dokan-dash-single" style="background:#fff3e0;">' +
                '<h3>' + (products.total_products || 0) + '</h3><p>商品总数</p>' +
            '</div>' +
            '<div class="dokan-dash-single" style="background:' + (totalAlerts > 0 ? '#ffebee' : '#e8f5e9') + ';">' +
                '<h3>' + totalAlerts + '</h3><p>库存预警</p>' +
            '</div>';

        $('#12fz-dash-overview').html(html);
    }

    function renderAlerts(alerts) {
        if (!alerts || (alerts.low_stock === 0 && alerts.out_of_stock === 0)) {
            $('#12fz-alerts-list').html('<p style="color:green;"><i class="fa fa-check-circle"></i> 所有库存正常</p>');
            return;
        }

        var html = '';
        if (alerts.out_of_stock > 0) {
            html += '<p style="color:red;"><i class="fa fa-exclamation-triangle"></i> ' + alerts.out_of_stock + ' 个商品缺货</p>';
        }
        if (alerts.low_stock > 0) {
            html += '<p style="color:orange;"><i class="fa fa-exclamation-circle"></i> ' + alerts.low_stock + ' 个商品库存偏低</p>';
        }
        $('#12fz-alerts-list').html(html);
    }

    function renderSalesChart(sales) {
        if (!sales || !sales.daily || sales.daily.length === 0) {
            $('#12fz-sales-chart').html('<p style="text-align:center; color:#999;">暂无销售数据</p>');
            return;
        }

        var html = '<table class="dokan-table dokan-table-striped">' +
            '<thead><tr><th>日期</th><th>订单数</th><th>销售额</th></tr></thead><tbody>';
        $.each(sales.daily, function(i, row) {
            html += '<tr><td>' + row.date + '</td><td>' + row.orders + '</td><td>¥' + parseFloat(row.total_sales).toFixed(2) + '</td></tr>';
        });
        html += '</tbody></table>' +
            '<p style="text-align:right; margin-top:10px;">' +
            '平均客单价: ¥' + (sales.average_order || '0.00') + 
            ' | 总销售额: ¥' + (sales.total_sales || '0').toLocaleString() + '</p>';

        $('#12fz-sales-chart').html(html);
    }

    $('#12fz-dash-period').change(function() {
        loadDashboard($(this).val());
    });

    $('#12fz-dash-refresh').click(function() {
        $.post(ajaxurl, {
            action: '12fz_refresh_dashboard',
            nonce: twelveFzData.nonce
        }, function(res) {
            if (res.success) {
                loadDashboard(currentPeriod);
                dokan_sweetalert('数据已刷新', {icon:'success'});
            }
        });
    });

    loadDashboard('monthly');
    setInterval(function() { loadDashboard(currentPeriod); }, 120000);
});
</script>
