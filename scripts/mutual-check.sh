#!/bin/bash
# 12FZ Bot 互检脚本 — chaogu-ai 在 Vultr 运行
# 部署路径: /root/scripts/mutual-check.sh
# 调用: cron 每15分钟
# GitHub: docs/协作规则.md §9.2
#
# 检查目标: 服务器技术（阿里云）/ gong3（ESXi）
# 静默通过，异常则@飞书群提醒

set -e

# 配置
FEISHU_BOT_TOKEN=""  # 部署时填写，用飞书bot api发消息
FEISHU_GROUP_OPEN_ID=""  # 12FZ程序开发组

ALI_HOST="8.138.235.183"
ALI_USER="root"
ALI_PASS="Cx99w06020354"

GONG3_HOST="nps.12fz.com"
GONG3_PORT=222
GONG3_USER="qiuming"
GONG3_PASS="Cx99w@06020354"

SSH_OPTS="-o ConnectTimeout=15 -o StrictHostKeyChecking=no"
TRIP_FILE="/tmp/.mutual_check_trip"  # 连续失败计数

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')]" "$@"; }

notify_feishu() {
    local mention="$1"  # feishu open_id
    local msg="$2"
    if [ -n "$FEISHU_BOT_TOKEN" ] && [ -n "$FEISHU_GROUP_OPEN_ID" ]; then
        local text
        if [ -n "$mention" ]; then
            text="<at user_id=\"$mention\"></at> $msg"
        else
            text="$msg"
        fi
        curl -s -X POST "https://open.feishu.cn/open-apis/bot/v2/hook/$FEISHU_BOT_TOKEN" \
            -H "Content-Type: application/json" \
            -d "{\"msg_type\":\"text\",\"content\":{\"text\":\"🩺 $text\"}}" >/dev/null 2>&1 &
    fi
}

check_host() {
    local host="$1" port="$2" user="$3" pass="$4" label="$5" feishu_id="$6"
    local OUTPUT
    
    OUTPUT=$(sshpass -p "$pass" ssh $SSH_OPTS -p "$port" "$user@$host" "
        hermes_count=\$(ps aux | grep -c '[h]ermes' 2>/dev/null || echo 0)
        echo \"HERMES=\$hermes_count\"
        if [ -d /root/.hermes/logs ]; then
            for f in /root/.hermes/logs/*.log; do
                if [ -f \"\$f\" ]; then
                    size=\$(stat --format=%s \"\$f\" 2>/dev/null || echo 0)
                    echo \"LOG:\$(basename \$f):\$size\"
                fi
            done
        fi
        mem=\$(free | awk '/Mem/ {printf \"%d\", \$3/\$2 * 100}' 2>/dev/null || echo 0)
        echo \"MEM=\$mem\"
        disk=\$(df / | awk 'NR==2 {print +\$5}' 2>/dev/null || echo 0)
        echo \"DISK=\$disk\"
    " 2>/dev/null) || {
        log "❌ $label SSH连接失败"
        notify_feishu "$feishu_id" "SSH连接失败，请检查状态 🚨"
        return 1
    }
    
    local hermes_count=$(echo "$OUTPUT" | grep "^HERMES=" | cut -d= -f2)
    local mem_pct=$(echo "$OUTPUT" | grep "^MEM=" | cut -d= -f2)
    local disk_pct=$(echo "$OUTPUT" | grep "^DISK=" | cut -d= -f2)
    local alerts=""
    
    if [ -z "$hermes_count" ] || [ "$hermes_count" -lt 1 ]; then
        alerts="${alerts}Hermes进程缺失;"
    fi
    
    if [ -n "$mem_pct" ] && [ "$mem_pct" -gt 85 ] 2>/dev/null; then
        alerts="${alerts}内存${mem_pct}%;"
    fi
    
    if [ -n "$disk_pct" ] && [ "$disk_pct" -gt 85 ] 2>/dev/null; then
        alerts="${alerts}磁盘${disk_pct}%;"
    fi
    
    # 检查日志大小
    while IFS= read -r line; do
        if [[ "$line" == LOG:* ]]; then
            local log_name=$(echo "$line" | cut -d: -f2)
            local log_size_b=$(echo "$line" | cut -d: -f3)
            if [ -n "$log_size_b" ] && [ "$log_size_b" -gt 104857600 ] 2>/dev/null; then
                local log_size_mb=$((log_size_b / 1048576))
                alerts="${alerts}日志${log_name}=${log_size_mb}MB;"
            fi
        fi
    done <<< "$OUTPUT"
    
    if [ -n "$alerts" ]; then
        log "⚠️ $label 异常: $alerts"
        notify_feishu "$feishu_id" "检测到异常: $alerts"
        return 1
    else
        log "✅ $label 正常 (Hermes=$hermes_count, Mem=${mem_pct}%, Disk=${disk_pct}%)"
        return 0
    fi
}

# ---- 主流程 ----

log "【互检】开始..."

ali_result=0
gong3_result=0

check_host "$ALI_HOST" 22 "$ALI_USER" "$ALI_PASS" "服务器技术(阿里云)" "ou_7d72c033ce3e6bec28d7a1de4b3fa0a4" || ali_result=1
check_host "$GONG3_HOST" "$GONG3_PORT" "$GONG3_USER" "$GONG3_PASS" "gong3(ESXi)" "ou_8a5088e9ba6deeadd236fc45b95cc63a" || gong3_result=1

# 连续失败追踪
if [ "$ali_result" -eq 1 ] || [ "$gong3_result" -eq 1 ]; then
    local trip_count=0
    if [ -f "$TRIP_FILE" ]; then
        trip_count=$(cat "$TRIP_FILE" 2>/dev/null || echo 0)
    fi
    trip_count=$((trip_count + 1))
    echo "$trip_count" > "$TRIP_FILE"
    
    if [ "$trip_count" -ge 3 ]; then
        log "⚠️ 连续3次互检异常，通报老板"
        notify_feishu "" "连续3次(45分钟)检测到异常未修复 @老板 请关注 🔴"
        rm -f "$TRIP_FILE"
    fi
else
    rm -f "$TRIP_FILE" 2>/dev/null || true
fi

log "【互检】完成"
exit 0
