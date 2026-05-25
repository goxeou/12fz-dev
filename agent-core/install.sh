#!/bin/bash
# AgentCore 心跳客户端 — 一键部署脚本
# ==============================================
# 主源（GitHub）:
#   curl -sL https://raw.githubusercontent.com/goxeou/12fz-dev/master/agent-core/install.sh | bash
#
# 备用源（信号站）:
#   curl -sk https://signal.12fz.com/repo/agent-core/install.sh | bash
#
# 指定 Agent 名:
#   curl -sL ...install.sh | bash -s chaogu-ai
#   curl -sL ...install.sh | bash -s "服务器技术" dev
# ==============================================

set -e

# ── 参数 ───────────────────────────────────
AGENT_NAME="${1:-$(hostname)}"
TAGS="${2:-}"
GITHUB_RAW="https://raw.githubusercontent.com/goxeou/12fz-dev/master/agent-core"
BACKUP_URL="https://signal.12fz.com/repo/agent-core"
BACKUP_CURL="curl -sk"  # signal 证书不含此域名，需 -sk
API_URL="https://signal.12fz.com/heartbeat"
CLIENT_SCRIPT="heartbeat-client.py"

echo "=============================================="
echo " AgentCore 心跳客户端 — 一键部署"
echo " Agent: $AGENT_NAME"
echo " 标签: ${TAGS:-无}"
echo " GitHub: $GITHUB_RAW"
echo " 备用: $BACKUP_URL"
echo "=============================================="

# ── 下载客户端程序（双源容错） ────────────
mkdir -p ~/.hermes/scripts
TARGET="$HOME/.hermes/scripts/$CLIENT_SCRIPT"

download_file() {
    local url="$1"
    local out="$2"
    if command -v curl &>/dev/null; then
        curl -sL -o "$out" "$url" 2>/dev/null
    elif command -v wget &>/dev/null; then
        wget -q -O "$out" "$url" 2>/dev/null
    else
        return 1
    fi
    [ -s "$out" ]
}

download_file_insecure() {
    local url="$1"
    local out="$2"
    if command -v curl &>/dev/null; then
        curl -sk -o "$out" "$url" 2>/dev/null
    elif command -v wget &>/dev/null; then
        wget -q --no-check-certificate -O "$out" "$url" 2>/dev/null
    else
        return 1
    fi
    [ -s "$out" ]
}

echo "📥 下载心跳客户端..."

# 主源：GitHub（带SSL验证）
if download_file "$GITHUB_RAW/$CLIENT_SCRIPT" "$TARGET"; then
    echo "   ✅ GitHub 下载成功"
else
    echo "   ⚠️ GitHub 不可达，尝试备用源..."
    # 备用源：signal.12fz.com（证书未包含signal域名，跳过SSL）
    if download_file_insecure "$BACKUP_URL/$CLIENT_SCRIPT" "$TARGET"; then
        echo "   ✅ 备用源下载成功"
    else
        echo "   ❌ 所有源下载失败，请检查网络"
        exit 1
    fi
fi

chmod +x "$TARGET"
echo "   ✅ 已保存: $TARGET"

# ── 写入配置 ───────────────────────────────
TAG_ARRAY="[]"
if [ -n "$TAGS" ]; then
    TAG_ARRAY="[\"$TAGS\"]"
fi

cat > ~/.hermes/agent-heartbeat.json << EOF
{
  "agent_name": "$AGENT_NAME",
  "tags": $TAG_ARRAY,
  "platform": "linux",
  "auto_heal": true,
  "gateway_check": ["hermes.*gateway"],
  "startup_cmd": ""
}
EOF
echo "✅ 配置已写入 ~/.hermes/agent-heartbeat.json"

# ── 测试连接 ──────────────────────────────
echo "🔍 测试心跳服务连接..."
if curl -sk "$API_URL/health" >/dev/null 2>&1; then
    echo "   ✅ 连接成功"
else
    echo "   ⚠️ 连接失败，部署后会自动重试"
fi

# ── 注册为 systemd 服务 ──────────────────
echo "📦 注册 systemd 服务..."

SERVICE_CONTENT="[Unit]
Description=AgentCore 心跳客户端 ($AGENT_NAME)
After=network.target

[Service]
Type=simple
User=$(whoami)
ExecStart=/usr/bin/python3 $HOME/.hermes/scripts/$CLIENT_SCRIPT
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target"

# 写入服务文件（需要 root 或 sudo）
if [ "$(id -u)" = "0" ]; then
    cat > /etc/systemd/system/agent-hb-client.service <<< "$SERVICE_CONTENT"
    systemctl daemon-reload
    systemctl enable agent-hb-client
    systemctl restart agent-hb-client
else
    echo "$SERVICE_CONTENT" | sudo tee /etc/systemd/system/agent-hb-client.service >/dev/null
    sudo systemctl daemon-reload
    sudo systemctl enable agent-hb-client
    sudo systemctl restart agent-hb-client
fi

echo "✅ systemd 服务已启动"
sleep 2

# ── 验证 ──────────────────────────────────
echo ""
echo "=== 验证 ==="
systemctl status agent-hb-client --no-pager 2>&1 | head -8
echo ""

# 等一次心跳
sleep 3
echo "=== 心跳状态 ==="
curl -sk "$API_URL/status" 2>/dev/null | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
    for name, info in d.get('agents', {}).items():
        icon = '🟢' if info.get('online') else '🔴'
        print(f'{icon} {name}: {info.get(\"last_heartbeat\", \"?\")[-8:]}')
except:
    print('  等待首次心跳...')
" 2>/dev/null || echo "  等待首次心跳..."

echo ""
echo "=============================================="
echo " ✅ 部署完成！"
echo "  Agent: $AGENT_NAME"
echo "  查看状态: systemctl status agent-hb-client"
echo "  查看日志: tail -f ~/.hermes/heartbeat-client.log"
echo "  在线面板: $API_URL/status"
echo "=============================================="
