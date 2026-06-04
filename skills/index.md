# 12FZ 中央技能库

> 所有Bot的技能统一托管在 GitHub: `goxeou/12fz-dev/skills/`
> 同步脚本: `skills/sync.sh`
> 协作规则: `docs/协作规则.md`

---

## 分类

### 🟢 公共技能（所有Bot共享）

| 技能 | 目录 | 用途 |
|------|------|------|
| `team-protocol` | `shared/team-protocol/` | 协作规则摘要、沟通规范、值班制度 |
| `health-check` | `shared/health-check/` | 自检/互检流程、监控配置 |
| `ops-backup` | `shared/ops-backup/` | 备份/恢复流程、灾难恢复预案 |
| `server-info` | `shared/server-info/` | 服务器清单、域名映射（无密码） |
| `code-review-graph` | `shared/code-review-graph/` | 代码依赖知识图，AI改代码自动查影响半径 |

### 🟡 角色技能（按角色分配）

| 技能 | 目录 | 谁用 | 用途 |
|------|------|------|------|
| `chaogu-ai` | `roles/chaogu-ai/` | chaogu-ai | 交易策略监控、代码审查、交易风控 |
| `server-tech` | `roles/server-tech/` | 服务器技术 | 前端开发规范、阿里云运维流程 |
| `gong3` | `roles/gong3/` | gong3 | 后端开发规范、ESXi运维流程 |

### 🔴 敏感技能（仅本地，不上GitHub）

| 技能 | 机器 | 原因 |
|------|------|------|
| `team-member-server-tech` | 三台都装 | 含SSH密码、API密钥 |
| `team-member-gong3` | 三台都装 | 含SSH密码、内网信息 |

---

## 使用方式

### 三台机器统一同步

```bash
# 手动同步一次
bash /root/scripts/skill-sync.sh

# cron自动同步（已集成到 health-check 自检脚本中）
# 每10分钟自检时自动检查GitHub是否有新技能版本
```

### 创建新技能

1. 在 `skills/shared/` 或 `skills/roles/` 下创建 `<skill-name>/SKILL.md`
2. 更新 `skills/index.md`
3. 推送到GitHub
4. 自检脚本在10分钟内自动同步到各机器

### 模版

参考 `skills/template/SKILL.md`

---

## 技能命名规范

- 小写字母 + 短横线（kebab-case）
- 公共技能用领域名（如 `team-protocol`）
- 角色技能用角色名（如 `server-tech`）
- 敏感技能加 `private-` 前缀（不上GitHub）

---

> 维护于 GitHub: `goxeou/12fz-dev/skills/index.md`
