---
name: health-check
description: 12FZ Bot健康监控系统 — 自检/互检/兜底。三层防线确保无人掉线无人发现。
---

# Bot健康监控

> 全量规范见 `docs/协作规则.md` 第九节

## 第一层：自检（每台Bot自己盯自己）

**脚本:** `/root/scripts/health-check.sh`
**频率:** cron `*/10 * * * *`
**来自:** GitHub `scripts/health-check.sh`

检查项：
- Hermes进程 ≥ 1（不在则自动重启）
- 日志文件 < 100MB（超则截断归档）
- 内存使用 < 80%（超则清理缓存）
- 磁盘使用 < 85%（超则清理/tmp）

自检通过 → 静默
自检异常 → 自动恢复 + 群内通知

## 第二层：互检（chaogu-ai监控所有人）

**脚本:** `/root/scripts/mutual-check.sh`
**频率:** cron `*/15 * * * *`
**运行在:** chaogu-ai（Vultr）

检查目标：
- 服务器技术（阿里云）— SSH查Hermes/日志/内存/磁盘
- gong3（ESXi）— 同上，通过NPS 222隧道
- 连续3次异常未修复 → 群内@老板

## 第三层：兜底

- chaogu-ai超过1小时无督导 → 服务器技术远程查Vultr
- 全部沉默超过2小时 → 老板手动重启

## 技能同步

`/root/scripts/skill-sync.sh` 集成在自检脚本中，每次自检时自动拉取GitHub最新技能。
