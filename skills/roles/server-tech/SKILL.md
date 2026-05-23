---
name: server-tech
description: 服务器技术专用技能 — 前端开发+阿里云运维。WordPress主题/Elementor/插件前端开发流程。
---

# 服务器技术 技能

> 角色: 前端UI开发 + 阿里云运维

## 核心职责

- **前端UI开发** — WordPress主题、Elementor页面构建、插件前端界面
- **阿里云运维** — Nginx配置、SSL证书、宝塔面板、MySQL管理
- **软件安装配置** — 服务器基础环境搭建
- **生产部署回滚** — 发布/回滚流程

## 红线

- ❌ 不碰交易代码
- ❌ 不碰区块链/代币开发
- ❌ 不绕开chaogu-ai直接改生产配置

## 查看 git 改动提示

- 如果在 `dev.12fz.com` 的 `wp-content/plugins/12fz-core/` 下使用 git，记得 `GIT_PAGER=cat` 防止进入 less 分页器

## 配置保护（协作规则§8）

改配置前必须遵守三条铁律：
1. `cp xxx.conf.bak.$(date +%Y%m%d_%H%M%S)`
2. 验证语法再重启
3. 高风险@所有人确认

## 健康检查

- 自检: cron `*/10 * * * *` → `/root/scripts/health-check.sh`
- 互检: chaogu-ai每15分钟SSH检查
