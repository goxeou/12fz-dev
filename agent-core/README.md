# AgentCore 心跳客户端

AgentCore 定时心跳上报系统，每个 Agent 向心跳服务定时上报状态。

## 快速安装

```bash
# 主源（GitHub）
curl -sL https://raw.githubusercontent.com/goxeou/12fz-dev/main/agent-core/install.sh | bash

# 备用源（signal.12fz.com，国内访问更快）
curl -sL https://signal.12fz.com/repo/agent-core/install.sh | bash
```

## 指定 Agent 名称

```bash
# 不指定则用 hostname
curl -sL https://raw.githubusercontent.com/goxeou/12fz-dev/main/agent-core/install.sh | bash -s chaogu-ai

# 带标签
curl -sL https://raw.githubusercontent.com/goxeou/12fz-dev/main/agent-core/install.sh | bash -s 服务器技术 dev
```

## 验证

```bash
# 查看服务状态
systemctl status agent-hb-client

# 查看心跳日志
tail -f ~/.hermes/heartbeat-client.log

# 在线状态面板
curl -s https://signal.12fz.com/heartbeat/status
```

## 文件说明

| 文件 | 说明 |
|------|------|
| `install.sh` | 一键安装脚本 |
| `heartbeat-client.py` | 心跳客户端程序 |
| `README.md` | 本文件 |

## 配置

安装后编辑 `~/.hermes/agent-heartbeat.json`：

```json
{
  "agent_name": "你的Agent名",
  "tags": ["main-agent"],
  "platform": "linux",
  "auto_heal": true,
  "gateway_check": ["hermes.*gateway"],
  "startup_cmd": "hermes gateway run --profile my-agent &"
}
```
