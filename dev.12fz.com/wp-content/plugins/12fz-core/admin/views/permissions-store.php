<div class="dokan-dashboard-wrap">
    <?php dokan_get_template('dashboard-nav.php', ['active_menu' => '12fz-staff']); ?>

    <div class="dokan-dashboard-content">
        <header class="dokan-dashboard-header">
            <h1 class="entry-title">
                <?php esc_html_e('员工管理', '12fz-core'); ?>
                <button class="dokan-btn dokan-btn-theme dokan-right" id="12fz-add-staff-btn">
                    <?php esc_html_e('添加员工', '12fz-core'); ?>
                </button>
            </h1>
        </header>

        <!-- 添加员工弹窗 -->
        <div id="12fz-staff-form" style="display:none; margin-bottom:20px; padding:15px; background:#f9f9f9; border:1px solid #ddd;">
            <form id="12fz-staff-form-inner">
                <div class="dokan-form-group">
                    <label>员工邮箱</label>
                    <input type="email" name="email" class="dokan-form-control" required placeholder="输入员工邮箱地址">
                    <p class="description">如果邮箱尚未注册，系统将自动创建账号并通过邮件发送密码。</p>
                </div>

                <div class="dokan-form-group">
                    <label>角色</label>
                    <select name="role" class="dokan-form-control">
                        <option value="cashier">收银员</option>
                        <option value="inventory_clerk">库存管理员</option>
                        <option value="viewer">仅查看</option>
                    </select>
                </div>

                <div class="dokan-form-group">
                    <button type="submit" class="dokan-btn dokan-btn-theme">添加</button>
                    <button type="button" class="dokan-btn dokan-btn-default" id="12fz-cancel-staff">取消</button>
                </div>
            </form>
        </div>

        <!-- 角色说明 -->
        <div class="dokan-alert dokan-alert-info" style="margin-bottom:15px;">
            <strong>角色说明：</strong>
            <ul style="margin:5px 0 0 15px;">
                <li><strong>门店经理</strong> - 完整权限，可管理员工</li>
                <li><strong>收银员</strong> - 可处理订单和POS收银，不能管理商品和员工</li>
                <li><strong>库存管理员</strong> - 可管理商品和库存，不能处理订单</li>
                <li><strong>仅查看</strong> - 只能查看看板和报表</li>
            </ul>
        </div>

        <table class="dokan-table dokan-table-striped">
            <thead>
                <tr>
                    <th>姓名</th>
                    <th>邮箱</th>
                    <th>角色</th>
                    <th>加入时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="12fz-staff-table">
                <tr><td colspan="5" style="text-align:center;">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    var roles = {};

    function loadStaff() {
        $.get(ajaxurl, {
            action: '12fz_get_store_staff',
            nonce: twelveFzData.nonce
        }, function(res) {
            if (!res.success) return;
            roles = res.data.roles || {};
            var html = '';
            if (!res.data.staff || res.data.staff.length === 0) {
                html = '<tr><td colspan="5" style="text-align:center;">暂无员工</td></tr>';
            } else {
                $.each(res.data.staff, function(i, s) {
                    html += '<tr>' +
                        '<td>' + (s.display_name || '未知') + '</td>' +
                        '<td>' + s.user_email + '</td>' +
                        '<td><select class="dokan-form-control input-sm role-select" data-id="' + s.id + '">';
                    $.each(roles, function(key, label) {
                        html += '<option value="' + key + '" ' + (s.role === key ? 'selected' : '') + '>' + label + '</option>';
                    });
                    html += '</select></td>' +
                        '<td>' + (s.created_at || '-') + '</td>' +
                        '<td><button class="dokan-btn dokan-btn-sm dokan-btn-danger remove-staff" data-id="' + s.id + '">移除</button></td>' +
                    '</tr>';
                });
            }
            $('#12fz-staff-table').html(html);
        });
    }

    $('#12fz-add-staff-btn').click(function() {
        $('#12fz-staff-form').slideToggle();
    });

    $('#12fz-cancel-staff').click(function() {
        $('#12fz-staff-form').slideUp();
        $('#12fz-staff-form-inner')[0].reset();
    });

    $('#12fz-staff-form-inner').submit(function(e) {
        e.preventDefault();
        var data = {
            action: '12fz_add_store_staff',
            nonce: twelveFzData.nonce,
            email: $('[name="email"]').val(),
            role: $('[name="role"]').val()
        };

        $.post(ajaxurl, data, function(res) {
            if (res.success) {
                $('#12fz-staff-form').slideUp();
                $('#12fz-staff-form-inner')[0].reset();
                loadStaff();
                dokan_sweetalert('员工添加成功', {icon:'success'});
            } else {
                dokan_sweetalert(res.data.message || '添加失败', {icon:'error'});
            }
        });
    });

    $(document).on('change', '.role-select', function() {
        var $this = $(this);
        $.post(ajaxurl, {
            action: '12fz_update_staff_role',
            nonce: twelveFzData.nonce,
            staff_id: $this.data('id'),
            role: $this.val()
        }, function(res) {
            if (res.success) {
                dokan_sweetalert('角色已更新为: ' + res.data.role_name, {icon:'success'});
            }
        });
    });

    $(document).on('click', '.remove-staff', function() {
        if (!confirm('确定移除该员工？')) return;
        $.post(ajaxurl, {
            action: '12fz_remove_store_staff',
            nonce: twelveFzData.nonce,
            staff_id: $(this).data('id')
        }, function(res) {
            if (res.success) loadStaff();
        });
    });

    loadStaff();
});
</script>
