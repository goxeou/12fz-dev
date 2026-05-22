<div class="dokan-dashboard-wrap">
    <?php dokan_get_template('dashboard-nav.php', ['active_menu' => '12fz-pricing']); ?>

    <div class="dokan-dashboard-content">
        <header class="dokan-dashboard-header">
            <h1 class="entry-title">
                <?php esc_html_e('定价规则', '12fz-core'); ?>
                <button class="dokan-btn dokan-btn-theme dokan-right" id="12fz-add-rule-btn">
                    <?php esc_html_e('添加规则', '12fz-core'); ?>
                </button>
            </h1>
        </header>

        <div id="12fz-pricing-form" style="display:none; margin-bottom:20px; padding:15px; background:#f9f9f9; border:1px solid #ddd;">
            <form id="12fz-pricing-form-inner">
                <input type="hidden" name="rule_id" value="0">
                
                <div class="dokan-form-group">
                    <label>规则名称</label>
                    <input type="text" name="rule_name" class="dokan-form-control" required>
                </div>

                <div class="dokan-form-group">
                    <label>规则类型</label>
                    <select name="rule_type" class="dokan-form-control">
                        <option value="tiered">阶梯定价</option>
                        <option value="volume">批量折扣</option>
                        <option value="role_based">用户角色定价</option>
                    </select>
                </div>

                <div class="dokan-form-group">
                    <label>折扣类型</label>
                    <select name="discount_type" class="dokan-form-control">
                        <option value="percentage">百分比(%)</option>
                        <option value="fixed">固定金额减免</option>
                        <option value="fixed_price">固定价格</option>
                    </select>
                </div>

                <div class="dokan-form-group">
                    <label>折扣值</label>
                    <input type="number" name="discount_value" class="dokan-form-control" step="0.01" min="0">
                </div>

                <div class="dokan-form-group">
                    <label>优先级 (数字越小优先级越高)</label>
                    <input type="number" name="priority" class="dokan-form-control" value="10">
                </div>

                <div class="dokan-form-inline">
                    <div class="dokan-form-group">
                        <label>开始时间</label>
                        <input type="datetime-local" name="start_date" class="dokan-form-control">
                    </div>
                    <div class="dokan-form-group">
                        <label>结束时间</label>
                        <input type="datetime-local" name="end_date" class="dokan-form-control">
                    </div>
                </div>

                <div class="dokan-form-group">
                    <button type="submit" class="dokan-btn dokan-btn-theme">保存规则</button>
                    <button type="button" class="dokan-btn dokan-btn-default" id="12fz-cancel-rule">取消</button>
                </div>
            </form>
        </div>

        <table class="dokan-table dokan-table-striped">
            <thead>
                <tr>
                    <th>名称</th>
                    <th>类型</th>
                    <th>折扣</th>
                    <th>优先级</th>
                    <th>有效期</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="12fz-rules-table">
                <!-- JS渲染 -->
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    var ajaxData = {action: '12fz_get_pricing_rules', nonce: twelveFzData.nonce};

    function loadRules() {
        $.get(ajaxurl, ajaxData, function(res) {
            if (!res.success) return;
            var html = '';
            $.each(res.data, function(i, r) {
                var discounts = JSON.parse(r.discounts || '[]');
                var discText = '';
                $.each(discounts, function(j, d) {
                    discText += (d.type === 'percentage' ? d.value + '%' : '¥' + d.value) + ' ';
                });
                html += '<tr>' +
                    '<td>' + r.rule_name + '</td>' +
                    '<td>' + r.rule_type + '</td>' +
                    '<td>' + (discText || '-') + '</td>' +
                    '<td>' + r.priority + '</td>' +
                    '<td>' + (r.start_date || '-') + ' ~ ' + (r.end_date || '-') + '</td>' +
                    '<td><span class="dokan-label dokan-label-' + (r.status === 'active' ? 'success' : 'default') + '">' + r.status + '</span></td>' +
                    '<td><button class="dokan-btn dokan-btn-sm dokan-btn-danger delete-rule" data-id="' + r.id + '">删除</button></td>' +
                '</tr>';
            });
            $('#12fz-rules-table').html(html || '<tr><td colspan="7" style="text-align:center">暂无定价规则</td></tr>');
        });
    }

    $('#12fz-add-rule-btn').click(function() {
        $('#12fz-pricing-form').slideToggle();
    });

    $('#12fz-cancel-rule').click(function() {
        $('#12fz-pricing-form').slideUp();
        $('#12fz-pricing-form-inner')[0].reset();
        $('[name="rule_id"]').val(0);
    });

    $('#12fz-pricing-form-inner').submit(function(e) {
        e.preventDefault();
        var data = {
            action: '12fz_save_pricing_rule',
            nonce: twelveFzData.nonce,
            rule_id: $('[name="rule_id"]').val(),
            rule_name: $('[name="rule_name"]').val(),
            rule_type: $('[name="rule_type"]').val(),
            priority: $('[name="priority"]').val(),
            discounts: JSON.stringify([{type: $('[name="discount_type"]').val(), value: $('[name="discount_value"]').val()}]),
            start_date: $('[name="start_date"]').val(),
            end_date: $('[name="end_date"]').val(),
            status: 'active'
        };

        $.post(ajaxurl, data, function(res) {
            if (res.success) {
                $('#12fz-pricing-form').slideUp();
                $('#12fz-pricing-form-inner')[0].reset();
                $('[name="rule_id"]').val(0);
                loadRules();
                dokan_sweetalert('规则保存成功', {icon:'success'});
            } else {
                dokan_sweetalert('保存失败', {icon:'error'});
            }
        });
    });

    $(document).on('click', '.delete-rule', function() {
        if (!confirm('确定删除此规则？')) return;
        $.post(ajaxurl, {
            action: '12fz_delete_pricing_rule',
            nonce: twelveFzData.nonce,
            rule_id: $(this).data('id')
        }, function(res) {
            if (res.success) loadRules();
        });
    });

    loadRules();
});
</script>
