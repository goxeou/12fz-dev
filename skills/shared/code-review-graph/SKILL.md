---
name: code-review-graph
description: 代码依赖知识图（code-review-graph）— 解析代码库为函数/类/依赖关系图，AI改代码时自动分析影响半径（blast radius），解决"改一个BUG出一堆BUG"问题。
tags: [code-review, mcp, blast-radius, ai-coding]
related_skills: [team-protocol, infrastructure-sync, native-mcp]
---

# code-review-graph — 代码依赖知识图

> GitHub: [tirth8205/code-review-graph](https://github.com/tirth8205/code-review-graph) (18k ⭐)
> 中英文文档: https://github.com/tirth8205/code-review-graph/blob/main/README.zh-CN.md

## 核心原理

AI改代码时经常"改了一个BUG，带出一堆BUG"，原因是AI不知道改的文件影响了哪些其他文件。code-review-graph 用 Tree-sitter 解析整个代码库，构建函数/类/调用关系的依赖图，改代码前先看"影响半径（blast radius）"——这颗手雷会炸到谁。

```
代码库 → Tree-sitter解析 → SQLite依赖图 → 改文件A → 自动追踪A的调用者/依赖/测试 → 只读受影响文件
```

## 效果数据

| 指标 | 数值 |
|------|------|
| 影响召回率 | **100%** — 不会漏掉受影响文件 |
| Token减少 | **38~528倍** — AI只读相关文件 |
| 增量更新 | **< 2秒** — 2900文件项目重索引不到2秒 |
| 支持语言 | **28种** — Go/Python/JS/TS/PHP/Rust/Java全部支持 |

## 安装步骤（每台机器执行）

### 1. 安装Python包

```bash
pip install code-review-graph
# 或 pipx install code-review-graph（推荐）
# 可选依赖（按需）：
pip install code-review-graph[all]  # 全部功能
pip install code-review-graph[embeddings]  # 语义搜索
pip install code-review-graph[communities]  # 社区检测（大型项目推荐）
```

### 2. 配置Hermes MCP集成

在 `~/.hermes/config.yaml` 的 `mcp_servers` 中添加：

```yaml
mcp_servers:
  code-review-graph:
    command: "code-review-graph"
    args: ["serve"]
    timeout: 120
    connect_timeout: 30
```

> 如果通过 `pipx` 安装，Hermes Agent会自动找到 `code-review-graph` 命令。
> 如果通过 `pip` 安装到虚拟环境，需要用 `uvx` 或完整路径：
> ```yaml
>   code-review-graph:
>     command: "uvx"
>     args: ["code-review-graph"]
> ```

重启Hermes Agent后，MCP工具自动生效（前缀 `mcp_code_review_graph_*`）。

### 3. 构建代码库依赖图

对每个项目仓库执行：

```bash
cd /path/to/your/project
code-review-graph install   # 自动检测AI工具并配置
code-review-graph build     # 解析代码库，构建依赖图
```

首次构建约10秒（500文件项目）。之后增量更新<2秒。

### 4. 配置自动更新（可选）

#### 方式A：Watch模式
```bash
code-review-graph watch     # 持续监听文件变更，自动更新图
```
建议后台运行：`nohup code-review-graph watch &`

#### 方式B：多仓库守护进程（推荐）
```bash
# 注册仓库
crg-daemon add /path/to/project-a --alias proj-a
crg-daemon add /path/to/project-b

# 启动守护进程（自动后台运行，每30秒健康检查）
crg-daemon start
```

## Agent在代码审核/开发中如何使用

### 场景1：改代码前查影响半径

当需要修改某个文件时，agent自动调用MCP工具查影响：

```
→ agent调用 mcp_code_review_graph_get_impact_radius
  参数: changed_files=["path/to/file.go"]
← 返回：该文件影响的所有调用者、依赖项、关联测试
```

### 场景2：提交前做变更风险分析

```
→ agent调用 mcp_code_review_graph_detect_changes
← 返回：风险评分 + 受影响函数列表 + 测试缺口
```

### 场景3：PR审核

```
→ agent调用 mcp_code_review_graph_get_review_context
← 返回：token优化的审核上下文 + 结构摘要
```

### 场景4：查依赖——谁调用了这个函数

```
→ agent调用 mcp_code_review_graph_query_graph
  参数: query="callers of FunctionX"
← 返回：所有调用者及其位置
```

### 所有可用MCP工具一览

| 工具 | 用途 |
|------|------|
| `mcp_code_review_graph_get_impact_radius` | 改文件前查影响半径 |
| `mcp_code_review_graph_get_review_context` | 审核上下文（token优化） |
| `mcp_code_review_graph_detect_changes` | 变更风险评分 |
| `mcp_code_review_graph_query_graph` | 查调用者/被调用者/测试 |
| `mcp_code_review_graph_get_minimal_context` | 超精简上下文（~100 tokens） |
| `mcp_code_review_graph_traverse_graph` | BFS/DFS图遍历 |
| `mcp_code_review_graph_list_graph_stats` | 图状态和健康度 |
| `mcp_code_review_graph_get_affected_flows` | 执行流受影响情况 |
| `mcp_code_review_graph_get_architecture_overview` | 架构概览 |
| `mcp_code_review_graph_get_hub_nodes` | 热点节点（高耦合处） |
| `mcp_code_review_graph_get_knowledge_gaps` | 知识缺口分析 |
| `mcp_code_review_graph_refactor` | 重命名预览/死代码检测 |
| 共30个工具 | 完整列表见官方文档 |

## 需要构建依赖图的仓库（12FZ）

| 仓库 | 路径建议 | 优先级 |
|------|---------|--------|
| `goxeou/12fz-sso` | SSO认证（Go） | ⭐⭐⭐ |
| `goxeou/12fz-erp` | ERP（Go） | ⭐⭐⭐ |
| `goxeou/12fz-chat` | 聊天系统（Go） | ⭐⭐（Phase 2） |
| `goxeou/12fz-ai` | AI服务层（Python） | ⭐⭐（Phase 2） |
| `goxeou/12fz-infra` | 基础设施 | ⭐（Docker/CI） |
| 旧项目（goxeou） | PHP项目 | ⭐（按需） |
| 12FZ WordPress | 设计师平台 | ⭐（按需） |

每个仓库需在clone的机器上执行 `code-review-graph build`。

## 团队分工

| 角色 | 负责机器 | 要装的仓库依赖图 |
|------|---------|----------------|
| **chaogu-ai** | Vultr (167.179.79.44) | AI服务层、文档、审核 |
| **gong3** | 101 (10.10.10.101) | SSO、ERP、聊天、旧PHP |
| **高级工程师** | 202 (10.10.10.202) | ERP、SSO |
| **服务器技术** | 旧阿里云 (8.138.235.183) | Infra、前端、WordPress |

## 验证安装

```bash
# 验证MCP工具可用
code-review-graph serve &
# 查看图统计
cd /path/to/project
code-review-graph status
# 输出示例:
# Nodes: 6,285 | Edges: 27,117 | Languages: Go, Python
# Flow detection: 128ms | Search latency: 1.5ms
```

在Hermes Agent中验证MCP集成：
```
问agent: "请调用 mcp_code_review_graph_list_graph_stats 查看当前项目图状态"
```

## 常见踩坑

1. **建图后修改文件，图不是最新的** — 每次改代码前先跑 `code-review-graph update`，或启用watch模式/守护进程
2. **MCP工具不可用** — 检查Hermes config.yaml中 `mcp_servers` 配置是否正确，重启Agent
3. **pip安装的code-review-graph找不到** — 确认PATH中含pip安装路径，或改用 `pipx install`
4. **大型项目首次build慢** — 正常，10-30秒；之后增量更新<2秒
5. **不自动监听文件变更** — 需要用 `crg-daemon start` 或 `code-review-graph watch` 后台运行

## 技能更新日志

| 日期 | 变更 |
|------|------|
| 2026-05-29 | 创建技能，基于 code-review-graph v2.3 |
