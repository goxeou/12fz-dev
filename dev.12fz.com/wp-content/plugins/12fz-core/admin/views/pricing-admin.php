<div class="wrap">
    <h1>12FZ 定价体系管理</h1>
    
    <div class="notice notice-info">
        <p>全局定价规则管理。卖家可在自己的Dokan面板中管理门店级定价规则。</p>
    </div>

    <div id="12fz-pricing-admin">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>规则名称</th>
                    <th>类型</th>
                    <th>卖家</th>
                    <th>优先级</th>
                    <th>生效时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody data-bind="rules">
                <!-- JS渲染 -->
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    function loadRules() {
        $.get(ajaxurl, {
            action: '12fz_get_pricing_rules',
            vendor_id: 0,
            _ajax_nonce: twelveFzData.nonce
        }, function(res) {
            if (res.success && res.data) {
                var html = '';
                $.each(res.data, function(i, r) {
                    html += '<tr>' +
                        '<td>' + r.rule_name + '</td>' +
                        '<td>' + r.rule_type + '</td>' +
                        '<td>#' + r.vendor_id + '</td>' +
                        '<td>' + r.priority + '</td>' +
                        '<td>' + (r.start_date || '-') + ' ~ ' + (r.end_date || '-') + '</td>' +
                        '<td>' + r.status + '</td>' +
                        '<td><button class="button button-small">编辑</button></td>' +
                    '</tr>';
                });
                $('tbody[data-bind="rules"]').html(html || '<tr><td colspan="7">暂无规则</td></tr>');
            }
        });
    }
    loadRules();
});
</script>
