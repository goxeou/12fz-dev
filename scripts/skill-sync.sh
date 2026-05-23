#!/bin/bash
# 12FZ 中央技能库同步脚本
# 部署路径: /root/scripts/skill-sync.sh
# 调用: 手动一次，或集成到 health-check.sh 自检脚本中
# 
# 作用:
#   1. 从GitHub拉取最新技能
#   2. 安装公共技能到 ~/.hermes/skills/
#   3. 根据本机角色安装角色技能
#   4. 不覆盖本地敏感技能（private-* 和 team-member-*）

set -e

SKILLS_DIR="/root/.skills-github"
HERMES_SKILLS_DIR="$HOME/.hermes/skills"
GIT_REPO_URL="https://github.com/goxeou/12fz-dev.git"
GIT_REPO="/root/12fz-dev"
BACKUP_DIR="/root/.skill-sync-bak"
LOG_FILE="/var/log/skill-sync.log"
TEMP_DIR="/tmp/.skills-sync-$$"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')]" "$@" | tee -a "$LOG_FILE"; }

cleanup() { rm -rf "$TEMP_DIR"; }
trap cleanup EXIT

# 检测代理环境变量（阿里云CentOS7走xray代理）
if [ -z "$http_proxy" ] && [ -z "$HTTP_PROXY" ]; then
    # 检查全局git代理
    GIT_HTTP_PROXY=$(git config --global http.proxy 2>/dev/null || true)
    if [ -n "$GIT_HTTP_PROXY" ]; then
        export http_proxy="$GIT_HTTP_PROXY"
        export https_proxy="$GIT_HTTP_PROXY"
        export HTTP_PROXY="$GIT_HTTP_PROXY"
        export HTTPS_PROXY="$GIT_HTTP_PROXY"
    fi
fi

# ---- 1. 获取技能源码 ----
log "🔄 获取 GitHub 最新技能..."

if [ -d "$GIT_REPO" ]; then
    # 完整仓库模式
    cd "$GIT_REPO"
    git pull origin master 2>>"$LOG_FILE" || log "⚠️ Git pull 失败"
    SKILLS_DIR="$GIT_REPO/skills"
else
    # 无仓库模式 — 直接从GitHub clone技能目录
    git clone --depth 1 --single-branch "$GIT_REPO_URL" "$TEMP_DIR" 2>>"$LOG_FILE" || {
        log "❌ Git clone 失败，技能同步跳过"
        exit 1
    }
    SKILLS_DIR="$TEMP_DIR/skills"
fi

# ---- 2. 安装公共技能（共享） ----
log "📦 安装公共技能..."
for skill_dir in "$SKILLS_DIR"/shared/*/; do
    skill_name=$(basename "$skill_dir")
    target="$HERMES_SKILLS_DIR/$skill_name"
    
    mkdir -p "$target"
    if [ -f "$skill_dir/SKILL.md" ]; then
        cp "$skill_dir/SKILL.md" "$target/SKILL.md"
        log "  ✅ 公共技能: $skill_name"
    fi
done

# ---- 3. 根据本机角色安装角色技能 ----
HOSTNAME=$(hostname)
log "🔍 本机: $HOSTNAME"

# 判断角色
ROLE=""
case "$HOSTNAME" in
    *vultr*|*Vultr*|*167*|*179*)   ROLE="chaogu-ai" ;;
    *aliyun*|*Aliyun*|*ali*|*138*) ROLE="server-tech" ;;
    *gong3*|*nps*|*222*)            ROLE="gong3" ;;
    *esxi*|*ESXi*)                  ROLE="gong3" ;;
    *)
        # 如果hostname判断失败，尝试通过可访问路径判断
        if [ -f "$HERMES_SKILLS_DIR/team-member-server-tech/SKILL.md" ]; then
            if grep -q "服务器技术" "$HERMES_SKILLS_DIR/team-member-server-tech/SKILL.md" 2>/dev/null; then
                ROLE="server-tech"
            elif grep -q "gong3" "$HERMES_SKILLS_DIR/team-member-gong3/SKILL.md" 2>/dev/null; then
                ROLE="gong3"
            fi
        fi
        [ -z "$ROLE" ] && ROLE="unknown"
        ;;
esac

log "  🏷️  识别角色: $ROLE"

if [ "$ROLE" != "unknown" ] && [ -d "$SKILLS_DIR/roles/$ROLE" ]; then
    target="$HERMES_SKILLS_DIR/$ROLE"
    mkdir -p "$target"
    if [ -f "$SKILLS_DIR/roles/$ROLE/SKILL.md" ]; then
        cp "$SKILLS_DIR/roles/$ROLE/SKILL.md" "$target/SKILL.md"
        log "  ✅ 角色技能: $ROLE"
    fi
else
    log "  ⏭️  跳过角色技能（$ROLE）"
fi

# ---- 4. 安装模板（可选） ----
if [ -d "$SKILLS_DIR/template" ] && [ -f "$SKILLS_DIR/template/SKILL.md" ]; then
    target="$HERMES_SKILLS_DIR/.template"
    mkdir -p "$target"
    cp "$SKILLS_DIR/template/SKILL.md" "$target/SKILL.md"
fi

# ---- 5. 验证 ----
log "🔍 验证技能安装..."
for skill in team-protocol health-check ops-backup server-info; do
    if [ -f "$HERMES_SKILLS_DIR/$skill/SKILL.md" ]; then
        log "  ✅ $skill 已安装"
    else
        log "  ⚠️  $skill 未安装或SKILL.md不存在"
    fi
done

if [ "$ROLE" != "unknown" ]; then
    if [ -f "$HERMES_SKILLS_DIR/$ROLE/SKILL.md" ]; then
        log "  ✅ 角色技能 $ROLE 已安装"
    else
        log "  ⚠️  角色技能 $ROLE 未安装"
    fi
fi

log "✅ 技能同步完成"
exit 0
