#!/usr/bin/env python3
"""
AgentCore 心跳客户端 v2.0
=========================
每个 Agent 定时向心跳服务上报心跳
自动注册 + 本地健康检查 + 自愈重启

GitHub: https://github.com/goxeou/12fz-dev/tree/main/agent-core
备用源: https://signal.12fz.com/repo/agent-core/

用法:
  python3 heartbeat-client.py              # 交互式运行
  # 或作为 systemd 服务 / cron 运行
"""

import os
import sys
import json
import time
import ssl
import signal
import subprocess
import urllib.request
import urllib.error
from datetime import datetime, timezone, timedelta

BJT = timezone(timedelta(hours=8))

# ── 配置（按需修改） ──────────────────────────────
HEARTBEAT_API = "https://signal.12fz.com/heartbeat/heartbeat"
STATUS_API = "https://signal.12fz.com/heartbeat/status"
HEALTH_API = "https://signal.12fz.com/heartbeat/health"

INTERVAL = 30       # 上报间隔（秒）
CONFIG_FILE = os.path.expanduser("~/.hermes/agent-heartbeat.json")
LOG_FILE = os.path.expanduser("~/.hermes/heartbeat-client.log")

# ── Agent 配置模板 ────────────────────────────────
# 各 Agent 在 ~/.hermes/agent-heartbeat.json 中填入以下内容：
# {
#   "agent_name": "chaogu-ai",          # 你的Agent名
#   "tags": ["main-agent", "审核"],      # 可选标签
#   "platform": "feishu",               # 平台
#   "auto_heal": true,                  # 自治愈
#   "gateway_check": ["hermes.*gateway"],  # 要检查的进程名
#   "startup_cmd": "hermes gateway run --profile chaogu-ai &"  # 重启命令
# }


def log(msg):
    ts = datetime.now(BJT).strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{ts}] {msg}"
    print(line)
    try:
        with open(LOG_FILE, "a") as f:
            f.write(line + "\n")
    except Exception:
        pass


def load_config():
    """读取配置"""
    default = {
        "agent_name": "",
        "tags": [],
        "platform": "",
        "auto_heal": False,
        "gateway_check": [],
        "startup_cmd": "",
    }
    try:
        if os.path.exists(CONFIG_FILE):
            with open(CONFIG_FILE) as f:
                return {**default, **json.load(f)}
    except Exception as e:
        log(f"[WARN] 读取配置失败: {e}")
    return default


def save_config_default():
    """创建默认配置模板"""
    cfg = {
        "agent_name": os.uname().nodename,
        "tags": [],
        "platform": "linux",
        "auto_heal": True,
        "gateway_check": ["hermes.*gateway"],
        "startup_cmd": "",
    }
    os.makedirs(os.path.dirname(CONFIG_FILE), exist_ok=True)
    with open(CONFIG_FILE, "w") as f:
        json.dump(cfg, f, ensure_ascii=False, indent=2)
    print(f"✅ 默认配置文件已创建: {CONFIG_FILE}")
    print("请编辑 agent_name 后重启")
    return cfg


def http_post(url, data):
    """HTTP POST 请求"""
    try:
        body = json.dumps(data).encode("utf-8")
        req = urllib.request.Request(
            url, data=body,
            headers={"Content-Type": "application/json"},
            method="POST"
        )
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        body = e.read().decode() if e.fp else ""
        log(f"[HTTP {e.code}] {url}: {body[:200]}")
        return None
    except Exception as e:
        log(f"[ERROR] {url}: {e}")
        return None


def http_get(url):
    """HTTP GET 请求"""
    try:
        req = urllib.request.Request(url)
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            return json.loads(resp.read().decode())
    except Exception as e:
        return None


def send_heartbeat(cfg):
    """发送心跳"""
    payload = {
        "agent_name": cfg["agent_name"],
        "tags": cfg.get("tags", []),
        "platform": cfg.get("platform", ""),
    }
    result = http_post(HEARTBEAT_API, payload)
    return result is not None


def check_process(patterns):
    """检查进程是否在运行"""
    for pattern in patterns:
        try:
            r = subprocess.run(
                ["pgrep", "-f", pattern],
                capture_output=True, text=True, timeout=5
            )
            if not r.stdout.strip():
                return False, pattern
        except Exception:
            return False, pattern
    return True, ""


def get_signal_file_status(agent_name):
    """从信号文件读取当前心跳状态（双通道备份）"""
    try:
        result = http_get(f"{STATUS_API}/{agent_name}")
        if result:
            return result.get("online", False), result.get("elapsed_seconds", 0)
    except Exception:
        pass
    return False, 999


def check_local_health(cfg):
    """检查本地健康状态"""
    results = {}

    # 网关进程检查
    patterns = cfg.get("gateway_check", [])
    if patterns:
        ok, failed = check_process(patterns)
        results["gateway"] = ok
        if not ok:
            results["gateway_failed"] = failed
    else:
        results["gateway"] = True

    # 网络连通性
    try:
        r = subprocess.run(
            ["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}",
             "-m", "5", "https://open.feishu.cn"],
            capture_output=True, text=True, timeout=10
        )
        results["network"] = r.stdout.strip() == "200"
    except Exception:
        results["network"] = False

    return results


def auto_heal(cfg, health):
    """自动重启失效的服务"""
    if not cfg.get("auto_heal"):
        return

    if not health.get("gateway") and health.get("gateway_failed"):
        failed = health["gateway_failed"]
        log(f"[HEAL] {failed} 未运行，尝试重启...")

        # 先杀残存进程
        subprocess.run(["pkill", "-f", failed], capture_output=True, timeout=5)
        time.sleep(2)

        # 执行启动命令
        startup = cfg.get("startup_cmd", "")
        if startup:
            subprocess.Popen(startup, shell=True)
            log(f"[HEAL] 已执行: {startup}")
        else:
            log(f"[HEAL] 未配置 startup_cmd，请添加")
            return

        # 等待3秒后检查是否启动成功
        time.sleep(3)
        ok, _ = check_process([failed])
        if ok:
            log(f"[HEAL] ✅ {failed} 重启成功")
        else:
            log(f"[HEAL] ❌ {failed} 重启失败")


def main():
    # 支持 --config 参数（systemd 可通过此参数指定不同配置文件）
    config_file = CONFIG_FILE
    if len(sys.argv) > 1 and sys.argv[1] == "--config" and len(sys.argv) > 2:
        config_file = sys.argv[2]

    cfg = load_config()

    # 首次运行：创建配置模板
    if not cfg["agent_name"]:
        cfg = save_config_default()
        sys.exit(0)

    log("=" * 50)
    log(f"AgentCore 心跳客户端 v2.0")
    log(f"Agent: {cfg['agent_name']}")
    log(f"API: {HEARTBEAT_API}")
    log(f"间隔: {INTERVAL}s")
    log("=" * 50)

    # 检查心跳服务是否可连
    health = http_get(HEALTH_API)
    if health:
        log(f"✅ 心跳服务在线 (v{health.get('version', '?')})")
    else:
        log(f"⚠️ 心跳服务不可达，将在 {INTERVAL}s 后重试")

    heartbeat_count = 0

    while True:
        try:
            now = datetime.now(BJT).strftime("%H:%M:%S")

            # 本地健康检查
            health = check_local_health(cfg)

            # 发送心跳
            ok = send_heartbeat(cfg)
            if ok:
                heartbeat_count += 1

            # 输出状态
            gw_icon = "🟢" if health.get("gateway", True) else "🔴"
            net_icon = "🟢" if health.get("network") else "🔴"
            hb_icon = "🟢" if ok else "🔴"

            log(f"[{now}] {hb_icon}心跳{heartbeat_count} | 网关{gw_icon} | 网络{net_icon}")

            # 自愈
            if not ok:
                log(f"  ⚠️ 心跳上报失败，将在{INTERVAL}s后重试")
            else:
                auto_heal(cfg, health)

        except KeyboardInterrupt:
            log("心跳客户端已停止")
            break
        except Exception as e:
            log(f"[ERROR] {e}")

        time.sleep(INTERVAL)


if __name__ == "__main__":
    main()
