---
name: vpn-proxy-setup
description: 12FZ翻墙/代理/科学上网全套方案 — xray REALITY服务端、microsocks代理、分流规则、Docker/Git/apt代理配置
---

# VPN & 代理设置技能

> **核心原则：国内直连，国外走代理。** 只翻墙不翻回来，降低封号风险。

## 架构总览

```
Vultr日本 (167.179.79.44)        阿里云广州 (8.138.235.183)        gong3 ESXi
┌─────────────────┐              ┌────────────────────┐        ┌────────────┐
│  xray REALITY   │  socks5/http │  microsocks:29008  │  nps   │  内网机器  │
│  port 8443      │◄─────────────│  本地代理:10809     │◄───────│            │
│  本地代理:10809  │              │  NPS隧道            │        └────────────┘
│  🔀分流路由      │              │  🔀分流路由          │        │            │
└────────┬────────┘              └────────────────────┘        └────────────┘
         │ xray客户端（手机/电脑）
         │ 🔀必须配分流：国内直连，国外走代理
```

## ⚠️ 防封铁律

1. **绝不全局代理** — 国内流量走代理=异常流量特征，增加封IP风险
2. **国内网站/API** → 直连（不走代理）
3. **国外资源**（GitHub、Docker Hub、npm、Google等）→ 走代理
4. **DNS** — 国内域名用国内DNS解析，国外域名用国外DNS
5. **域前置** — xray 已配 REALITY（SNI伪装成www.microsoft.com），不要改成明显非微软域名
6. **流量特征** — 避免长时间大流量穿透，阿里云会检测异常出口流量

## 方案A: xray REALITY VPN（Vultr日本）

### 服务端配置（已部署）

| 参数 | 值 | 防封作用 |
|------|-----|---------|
| 地址 | `web.goxeou.com` (Cloudflare → Vultr:8443) | Cloudflare 隐藏真实IP |
| 协议 | VLESS + XTLS Vision + REALITY | 流量无特征 |
| 回落 | www.microsoft.com:443 | TLS握手像访问微软 |
| SNI | `www.microsoft.com` | 防SNI检测 |

### 客户端配置（关键：必须配分流）

**v2rayN / Nekoray / v2rayNG 分流规则：**

```
地址: web.goxeou.com
端口: 8443
用户ID: de56b709-a55a-580e-8a72-f80f4338bd37
流控: xtls-rprx-vision
传输: tcp
伪装: reality
SNI: www.microsoft.com
指纹: chrome
ShortId: 993b07dae6acea9f

路由: 开启分流
  直连（国内）: geoip:cn, geosite:cn
  代理（国外）: 其他所有
```

**共享链接（仅用于导入，导入后必须配分流）：**
```
vless://de56b709-a55a-580e-8a72-f80f4338bd37@web.goxeou.com:8443?encryption=none&flow=xtls-rprx-vision&security=reality&sni=www.microsoft.com&fp=chrome&pbk=&sid=993b07dae6acea9f&type=tcp&headerType=none#12FZ-xray
```

### 服务端自己走代理（Vultr本机→10809端口）

在 Vultr 本机上，xray 监听 `127.0.0.1:10809` 作为本地 SOCKS5 代理。但 **不能全局设代理**，必须分流：

```bash
# ❌ 错误：全局代理
export http_proxy=http://127.0.0.1:10809  # 这会把所有流量走代理，包括国内API

# ✅ 正确：按需设置
# 只对特定命令/脚本使用
http_proxy=http://127.0.0.1:10809 curl -s https://api.github.com  # 国外
curl -s https://www.baidu.com  # 国内直连，不设代理
```

需要代理的常见场景：

| 场景 | 是否需代理 | 说明 |
|------|-----------|------|
| GitHub git clone/push | ✅ 需要 | GitHub被墙 |
| Docker Hub 拉镜像 | ✅ 需要（或用镜像加速器） | Docker Hub被墙 |
| apt apt-get update | ❌ 直连 | 阿里云镜像站国内 |
| pip install | ❌ 直连 | 阿里云PyPI镜像国内 |
| npm install | ❌ 直连 | 淘宝npm镜像国内 |
| 百度/阿里云API | ❌ 直连 | 国内服务 |
| 飞书API | ❌ 直连 | 国内服务 |

### 看门狗（自动保活）

已部署 cron `*/5 * * * *` 检测 xray 进程：
```bash
# /root/.hermes/cron/scripts/xray-watchdog.sh
pgrep -x xray || systemctl restart xray
```

## 方案B: microsocks代理（阿里云旧机→Vultr）

阿里云旧机（8.138.235.183）通过 microsocks 提供 SOCKS5 代理给内网机器：

| 参数 | 值 |
|------|-----|
| 端口 | 29008（仅内网/本机使用，不对外开放） |
| 协议 | SOCKS5（无认证） |

**重要：同样不能全局代理，必须分流。**

内网机器使用示例：
```bash
# ✅ 下载国外资源时走代理
http_proxy=socks5://172.31.244.36:29008 \
  curl -sL --max-time 30 https://github.com/xxx/repo.tar.gz

# ❌ 不要这样做 — 国内流量也走代理了
export http_proxy=socks5://172.31.244.36:29008  # 除非你知道你在做什么
```

## 方案C: 各应用代理配置（含分流）

### Git

```bash
# ✅ 全量走代理（GitHub必被墙，国内git服务少，可以全量走）
git config --global http.proxy http://127.0.0.1:10809
git config --global https.proxy http://127.0.0.1:10809

# 取消代理
git config --global --unset http.proxy
git config --global --unset https.proxy
```

### Docker 拉取镜像

**推荐：** 使用国内镜像加速器（不走代理，更快）：

```json
{
  "registry-mirrors": ["https://docker.1ms.run"]
}
```

**备选：** 大镜像（如Oracle XE 2.6GB）走代理可能超时，优先用镜像加速。

**不推荐全局代理：** Docker daemon 设全局代理会连国内registry（阿里云ACR、腾讯云TCR）都走代理，增加延迟和风险。

```bash
# 如果要配Docker代理，必须排除国内registry
Environment="NO_PROXY=localhost,127.0.0.1,::1,172.31.244.0/24,*.aliyuncs.com,*.docker.io,registry-1.docker.io,10.0.0.0/8"
```

### apt/yum

```bash
# ❌ 不需要代理 — 国内镜像站（mirrors.aliyun.com, mirrors.cloud.aliyuncs.com）直连
# 只配国内镜像源即可
```

### pip/npm

```bash
# ❌ 不需要代理 — 国内镜像站（aliyun PyPI, 淘宝npm）直连
# 只配国内镜像源即可
pip config set global.index-url https://mirrors.aliyun.com/pypi/simple/
npm config set registry https://registry.npmmirror.com/
```

## 分流规则速查表

| 目的地 | 走哪条路 | 说明 |
|--------|---------|------|
| github.com / raw.githubusercontent.com | 🔄 代理 | GitHub生态全翻 |
| docker.io / gvenzl/oracle-xe 等 | 🔄 代理 或 📦 镜像加速器 | Docker Hub |
| google.com / api.google / gstatic | 🔄 代理 | Google |
| npmjs.org / pypi.org | ❌ 直连（用镜像） | 国内镜像更快 |
| 阿里云/腾讯云/百度/飞书API | ❌ 直连 | 国内服务 |
| ubuntu.com / debian.org (apt源) | ❌ 直连（用镜像） | 国内镜像更快 |
| .cn 域名 | ❌ 直连 | 国内 |
| 172.31.244.0/24 / 10.0.0.0/8 | ❌ 直连 | 内网 |

## 故障排查

### 问题: 代理通了但Docker拉镜像慢/失败
大镜像（2GB+）走代理超时。优先用镜像加速器。

### 问题: microsocks端口能通但Docker连不上
Docker daemon的HTTP_PROXY不支持SOCKS5。如果只有SOCKS5代理可用，需额外装privoxy做SOCKS→HTTP桥接：
```bash
apt install privoxy
echo 'forward-socks5t / 127.0.0.1:29008 .' >> /etc/privoxy/config
systemctl restart privoxy
# 然后用 HTTP_PROXY=http://127.0.0.1:8118
```

### 问题: 国内ECS无法解析外部域名
国内ECS DNS可能被污染，只需对国外域名解析走代理，国内域名用国内DNS。不要全局改DNS。
