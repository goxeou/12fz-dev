#!/bin/bash
# 12FZ Bot Health Check — 自检脚本
# 部署路径: /root/scripts/health-check.sh
# 调用: cron 每10分钟
# GitHub: docs/协作规则.md §9.1
#
# 检查项: Hermes进程 | 日志大小 | 内存 | 磁盘
# 自检通过 → 静默
# 自动恢复成功 → 群内通知
# 自动恢复失败 → 群内@责任人

set -e

HOSTNAME=$(hostname)
HERMES_SERVICE="hermes"  # systemd service name
HOME="${HOME:-/root}"
LOG_DIR="$HOME/.hermes/logs"
mkdir -p "$LOG_DIR" 2>/dev/null || true
GATEWAY_LOG="$LOG_DIR/gateway.log"
MAX_LOG_MB=100
MAX_MEM_PCT=80
MAX_DISK_PCT=85
FEISHU_WEBHOOK=""  # 留空，由部署时填写

# ---- 工具函数 ----

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }

notify_feishu() {
    local msg="$1"
    if [ -n "$FEISHU_WEBHOOK" ]; then
        curl -s -X POST -H "Content-Type: application/json" \
            -d "{\"msg_type\":\"text\",\"content\":{\"text\":\"🩺 [$HOSTNAME] $msg\"}}" \
            "$FEISHU_WEBHOOK" >/dev/null 2>&1 &
    fi
}

# ---- 0. 技能同步（从GitHub拉取最新） ----
SKILL_SYNC_SCRIPT="/root/scripts/skill-sync.sh"
if [ -f "$SKILL_SYNC_SCRIPT" ]; then
    bash "$SKILL_SYNC_SCRIPT" >> /var/log/skill-sync.log 2>&1 &
fi

# ---- 1. 检查 Hermes 进程 ----

check_hermes() {
    local count
    count=$(ps aux | grep -c '[h]ermes' 2>/dev/null || echo 0)
    if [ "$count" -lt 1 ]; then
        log "⚠️ Hermes 进程不存在 (count=$count)，尝试重启..."
        systemctl restart hermes 2>/dev/null || systemctl restart hermes-agent 2>/dev/null || {
            log "❌ systemctl 重启失败"
            notify_feishu "Hermes进程挂了，自动重启失败，请手动检查 🚨"
            return 1
        }
        sleep 3
        count=$(ps aux | grep -c '[h]ermes' 2>/dev/null || echo 0)
        if [ "$count" -ge 1 ]; then
            log "✅ 重启成功 (count=$count)"
            notify_feishu "Hermes自动重启成功 ✅ ($count 进程)"
        else
            log "❌ 重启后进程仍不在"
            notify_feishu "Hermes自动重启后仍无进程 🚨"
            return 1
        fi
    fi
    return 0
}

# ---- 2. 检查日志大小 ----

check_logs() {
    if [ -d "$LOG_DIR" ]; then
        local large_files=0
        for f in "$LOG_DIR"/*.log; do
            if [ -f "$f" ]; then
                local size_mb
                size_mb=$(du -m "$f" 2>/dev/null | cut -f1)
                if [ "$size_mb" -gt "$MAX_LOG_MB" ] 2>/dev/null; then
                    log "⚠️ 日志文件过大: $(basename $f) = ${size_mb}MB (阈值${MAX_LOG_MB}MB)"
                    # 截断并归档
                    gzip -c "$f" > "${f}.$(date +%Y%m%d_%H%M%S).gz" 2>/dev/null
                    truncate -s 0 "$f" 2>/dev/null
                    log "✅ 已截断并归档: $(basename $f)"
                    large_files=$((large_files + 1))
                fi
            fi
        done
        if [ "$large_files" -gt 0 ]; then
            notify_feishu "日志文件超阈值，已截断归档 ($large_files 个)"
        fi
    fi
}

# ---- 3. 检查内存 ----

check_memory() {
    local mem_pct
    mem_pct=$(free | awk '/Mem/ {printf "%d", $3/$2 * 100}' 2>/dev/null || echo 0)
    if [ "$mem_pct" -gt "$MAX_MEM_PCT" ] 2>/dev/null; then
        log "⚠️ 内存使用: ${mem_pct}% (阈值${MAX_MEM_PCT}%)"
        # 清理缓存（不 kill 进程）
        sync && echo 3 > /proc/sys/vm/drop_caches 2>/dev/null || true
        sleep 1
        local after_pct
        after_pct=$(free | awk '/Mem/ {printf "%d", $3/$2 * 100}' 2>/dev/null || echo 0)
        log "✅ 清理缓存后: ${after_pct}%"
        if [ "$after_pct" -gt "$MAX_MEM_PCT" ] 2>/dev/null; then
            notify_feishu "内存使用 ${after_pct}%，清理缓存后仍超阈值 🚨"
        fi
    fi
}

# ---- 4. 检查磁盘 ----

check_disk() {
    local disk_pct
    disk_pct=$(df / | awk 'NR==2 {print +$5}' 2>/dev/null || echo 0)
    if [ "$disk_pct" -gt "$MAX_DISK_PCT" ] 2>/dev/null; then
        log "⚠️ 磁盘使用: ${disk_pct}% (阈值${MAX_DISK_PCT}%)"
        # 清理 /tmp 和 ~/.cache
        find /tmp -type f -atime +1 -delete 2>/dev/null || true
        find /root/.cache -type f -atime +7 -delete 2>/dev/null || true
        # 清理7天前的日志归档
        find "$LOG_DIR" -name "*.gz" -mtime +7 -delete 2>/dev/null || true
        local after_pct
        after_pct=$(df / | awk 'NR==2 {print +$5}' 2>/dev/null || echo 0)
        log "✅ 清理后: ${after_pct}%"
        if [ "$after_pct" -gt "$MAX_DISK_PCT" ] 2>/dev/null; then
            notify_feishu "磁盘使用 ${after_pct}%，清理后仍超阈值 🚨"
        fi
    fi
}

# ---- 主流程 ----

check_hermes
check_logs
check_memory
check_disk

log "自检完成 ✅"
exit 0
