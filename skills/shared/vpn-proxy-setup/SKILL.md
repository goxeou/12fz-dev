---
name: vpn-proxy-setup
description: 12FZ翻墙/代理/科学上网全套方案 — xray REALITY服务端、microsocks代理、Docker/Git/apt代理配置
---

# VPN & 代理设置技能

> 适用场景：从国内服务器（阿里云）需要访问海外资源（GitHub、Docker Hub、npm等）

## 架构总览

```
Vultr日本 (167.179.79.44)        阿里云广州 (8.138.235.183)        gong3 ESXi
┌─────────────────┐              ┌────────────────────┐        ┌────────────┐
│  xray REALITY   │  socks5/http │  microsocks:29008  │  nps   │  内网机器  │
│  port 8443      │◄─────────────│  本地代理:10809     │◄───────│            │
│  本地代理:10809  │              │  NPS隧道            │        └────────────┘
└────────┬────────┘              └────────────────────┘
         │ xray客户端（手机/电脑）
         │ 链接: vless://...@web.goxeou.com:8443?...
```

## 方案A: xray REALITY VPN（Vultr日本）

### 服务端（已部署）

本机 Vultr xray 配置：

| 参数 | 值 |
|------|-----|
| 地址 | `web.goxeou.com` (Cloudflare → Vultr:8443) |
| 端口 | 8443 |
| 协议 | VLESS + XTLS Vision + REALITY |
| 回落 | www.microsoft.com:443 |
| ServerName | `www.microsoft.com`, `microsoft.com` |

### 客户端配置

**Windows（v2rayN / Nekoray）：**

```
地址: web.goxeou.com
端口: 8443
用户ID: de56b709-a55a-580e-8a72-f80f4338bd37
流控: xtls-rprx-vision
传输: tcp
伪装: reality
SNI: www.microsoft.com
指纹: chrome
公钥: 无（REALITY不需要）
ShortId: 993b07dae6acea9f
```

**共享链接（分享给手机等设备）：**
```
vless://de56b709-a55a-580e-8a72-f80f4338bd37@web.goxeou.com:8443?encryption=none&flow=xtls-rprx-vision&security=reality&sni=www.microsoft.com&fp=chrome&pbk=&sid=993b07dae6acea9f&type=tcp&headerType=none#12FZ-xray
```

### 本地代理（127.0.0.1:10809）

在 Vultr 本机上，xray 客户端模式监听 `127.0.0.1:10809`（已配置），供本机脚本走代理：

```bash
# 设置环境变量
export http_proxy=http://127.0.0.1:10809
export https_proxy=http://127.0.0.1:10809

# 测试
curl -s --connect-timeout 5 https://www.google.com
```

### 看门狗（自动保活）

已部署 cron `*/5 * * * *` 检测 xray 进程，挂了自动重启：
```bash
# /root/.hermes/cron/scripts/xray-watchdog.sh
pgrep -x xray || systemctl restart xray
```

## 方案B: microsocks代理（阿里云旧机→Vultr）

阿里云旧机（8.138.235.183）上运行 microsocks SOCKS5 代理：

| 参数 | 值 |
|------|-----|
| 本地端口 | 29008（不对外开放） |
| 监听 | 0.0.0.0:29008（仅内网/本机使用） |
| 代理方式 | SOCKS5（无认证） |

**阿里云本机使用：**
```bash
# 直接在SSH会话中用
export http_proxy=socks5://127.0.0.1:29008
export https_proxy=socks5://127.0.0.1:29008

# 测试
curl -s --connect-timeout 5 https://www.google.com
```

**内网其他机器使用（172.31.244.0/24）：**
```bash
export http_proxy=socks5://172.31.244.36:29008
export https_proxy=socks5://172.31.244.36:29008
```

**注意：** Docker daemon 的 `HTTP_PROXY` 不支持 SOCKS5 协议，只能 HTTP/HTTPS 代理。

## 方案C: 各应用代理配置

### Git 配置代理

```bash
# 全局代理（推荐）
git config --global http.proxy http://127.0.0.1:10809
git config --global https.proxy http://127.0.0.1:10809

# 或 SOCKS5
git config --global http.proxy socks5://127.0.0.1:10809
git config --global https.proxy socks5://127.0.0.1:10809

# 取消代理
git config --global --unset http.proxy
git config --global --unset https.proxy

# 单次命令带代理
http_proxy=http://127.0.0.1:10809 git clone https://github.com/xxx/xxx.git
```

### Docker 拉取镜像（国内服务器）

**方法A: 配置镜像加速器（推荐）**
```json
{
  "registry-mirrors": ["https://docker.1ms.run"]
}
```

验证可用镜像加速器列表（2026-05测试）：
- `https://docker.1ms.run` ✅ 阿里云ECS可用
- `https://docker.m.daocloud.io` → 部分镜像返回403
- `https://docker.nju.edu.cn` → 部分镜像返回403
- `https://mirror.aliyuncs.com` → 需要开通ACR服务

**方法B: 通过代理拉取**
```bash
# 创建Docker systemd override
mkdir -p /etc/systemd/system/docker.service.d
cat > /etc/systemd/system/docker.service.d/proxy.conf << 'EOF'
[Service]
Environment="HTTP_PROXY=http://127.0.0.1:10809"
Environment="HTTPS_PROXY=http://127.0.0.1:10809"
Environment="NO_PROXY=localhost,127.0.0.1,::1,172.31.244.0/24,10.0.0.0/8"
EOF
systemctl daemon-reload && systemctl restart docker
```

**方法C: 通过内网转发（新机→旧机microsocks）**
```bash
# 配置Docker走旧机SOCKS5代理
# 注意：Docker daemon只支持HTTP代理，不支持SOCKS
# 需要额外装一个http代理工具来转发
```

### apt/yum 代理

**apt（Debian/Ubuntu）：**
```bash
echo 'Acquire::http::Proxy "http://127.0.0.1:10809";' > /etc/apt/apt.conf.d/proxy
echo 'Acquire::https::Proxy "http://127.0.0.1:10809";' >> /etc/apt/apt.conf.d/proxy
```

**yum（CentOS 7）：**
```bash
echo 'proxy=http://127.0.0.1:10809' >> /etc/yum.conf
```

### pip/npm 代理

```bash
# pip
pip install --proxy http://127.0.0.1:10809 package_name

# npm
npm config set proxy http://127.0.0.1:10809
npm config set https-proxy http://127.0.0.1:10809
```

## 故障排查

### 问题: 代理通了但Docker拉镜像慢/失败

通常因为Docker镜像过大（2GB+）走代理超时。优先用镜像加速器（方案C方法A）。

### 问题: microsocks端口能ping通但Docker连不上

Docker daemon的HTTP_PROXY不支持SOCKS5。如果只有SOCKS5代理可用，需额外装privoxy或tinyproxy做SOCKS→HTTP桥接：

```bash
apt install privoxy
echo 'forward-socks5t / 127.0.0.1:29008 .' >> /etc/privoxy/config
systemctl restart privoxy
# 然后用 HTTP_PROXY=http://127.0.0.1:8118
```

### 问题: aliyun ECS无法解析外部域名

国内ECS DNS可能被污染，换成公共DNS：
```bash
echo 'nameserver 8.8.8.8' >> /etc/resolv.conf
echo 'nameserver 1.1.1.1' >> /etc/resolv.conf
```
