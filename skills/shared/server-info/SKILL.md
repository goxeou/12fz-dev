---
name: server-info
description: 12FZ服务器清单（无密码版）。SSH密码等敏感信息在本地 team-member-* 技能中。
---

# 服务器信息

> 密码版详见本地 `team-member-server-tech` / `team-member-gong3` 技能

## 阿里云广州（生产环境）

| 属性 | 内容 |
|------|------|
| 用途 | WordPress主站 + MySQL + 宝塔面板 |
| 系统 | CentOS 7, 8核Xeon, 15GB RAM |
| 磁盘 | 40GB系统盘 + 1TB数据盘 |
| 服务 | Nginx, MySQL, Redis, NPS服务端, WordPress, WooCommerce |
| 站点 | www.12fz.com (正式), dev.12fz.com (开发) |
| 管理端口 | 宝塔42567, NPS后台19900 |

## Vultr日本（chaogu-ai所在）

| 属性 | 内容 |
|------|------|
| 用途 | VPN服务, 飞书中继, 交易策略执行 |
| 系统 | Ubuntu 22.04 (最近从ARM64迁移) |
| 配置 | 2核, 3.3GB RAM |
| 服务 | xray REALITY VPN, Hermes Agent, 飞书Bot, 交易脚本 |

## gong3内网（ESXi）

| 属性 | 内容 |
|------|------|
| 网络 | 10.10.10.0/24 内网 |
| 本机 | 10.10.10.101 (Ubuntu 22.04, 无sudo) |
| RDP | 10.10.10.111:3389 (gong3 Windows) |
| NAS | 10.10.10.8:5001 |
| NPS | 独立虚拟机跑NPC客户端，公网111.123.169.182 |

## 域名映射

| 域名 | 指向 | 用途 |
|------|------|------|
| www.12fz.com | Cloudflare → 阿里云 | 电商主站 |
| dev.12fz.com | Cloudflare → 阿里云 | 开发站 |
| goxeou.12fz.com | Cloudflare → 阿里云 | 格希欧ThinkPHP |
| nps.12fz.com | Cloudflare → 阿里云:19900/222等 | NPS穿透隧道 |
| web.goxeou.com | Cloudflare → Vultr:8443 | xray VPN域名 |
| 8.138.235.183 | — | 阿里云广州公网IP |
| 167.179.79.44 | — | Vultr日本公网IP |
