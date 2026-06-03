# suzao 从 Oracle 迁移到 PostgreSQL 方案 v2

> 决策：老板拍板 | 日期：2026-06-01
> 技术栈：Go + Java + PostgreSQL 18 + Ubuntu 26.04
> 目标机器：biancheng (10.10.10.202)
> 策略：**保守不替换，Oracle+PG并行运行，PG稳定后再切**

---

## 一、当前状态

```
生产站 (goxeou.12fz.com) — 不动，红线！！！
    |
开发站 (10.10.10.111 Windows) — Oracle XE (D:\SzJavaClient)
               源码 D:\develop (Go + Java)
    |
新环境 (10.10.10.202 Ubuntu 26.04 biancheng) — 全新建设
               目标: PostgreSQL 18 + pgvector
```

## 二、迁移策略：并行运行

```
Windows (111)                    Ubuntu (202)
┌──────────────┐                ┌──────────────┐
│  Oracle XE    │                │  PostgreSQL   │
│  (现有生产)   │                │  (新建)       │
│  源码 D:\dev  │   scp 源码 →   │  源码 /home/  │
└──────────────┘                └──────────────┘
        │                              │
        └──────── 双库并行 ────────────┘
        等待老板决定切换时机
```

## 三、执行步骤

### 第1步：环境搭建（服务器技术 → 今日可做）

在 biancheng (10.10.10.202) 上执行：

```bash
# 1. 安装 PostgreSQL 18 + pgvector
sudo apt update
sudo apt install -y postgresql postgresql-contrib golang-go openjdk-17-jdk git maven

# pgvector 扩展
sudo apt install -y postgresql-18-pgvector

# 2. 创建数据库和用户
sudo -u postgres psql <<EOF
CREATE USER suzao WITH PASSWORD 'Cx99w06020354';
CREATE DATABASE suzao_db OWNER suzao;
\c suzao_db
CREATE EXTENSION vector;
EOF

# 3. 配置远程访问（允许 Java/Go 连接）
echo "host    suzao_db    suzao    10.10.10.0/24    md5" >> /etc/postgresql/18/main/pg_hba.conf
sudo systemctl restart postgresql

# 4. 安装 ora2pg（数据迁移工具）
sudo apt install -y ora2pg
```

### 第2步：获取源码（服务器技术 + gong3）

```bash
# 从 Windows (111) 拷贝源码到 biancheng
scp -r gong3@10.10.10.111:D:\\develop /home/gong3/develop
```

### 第3步：代码适配（我 + 高级工程师 + gong3）

**数据库驱动改造：**
- Java: `ojdbc.jar` → `postgresql-42.x.x.jar`
- Go: `database/sql` + Oracle驱动 → `github.com/jackc/pgx/v5`

**SQL语法对照（Oracle → PostgreSQL）：**

| Oracle | PostgreSQL |
|--------|-----------|
| `NVL(a, b)` | `COALESCE(a, b)` |
| `SYSDATE` | `NOW()` |
| `ROWNUM` | `LIMIT` |
| `SEQUENCE.NEXTVAL` | `SERIAL` / `BIGSERIAL` |
| `MERGE INTO` | `INSERT ... ON CONFLICT` |
| `VARCHAR2(n)` | `VARCHAR(n)` |
| `NUMBER(p,s)` | `NUMERIC(p,s)` |
| `CLOB` | `TEXT` |

### 第4步：数据迁移（服务器技术 + 我）

```bash
# 1. Windows (111) 上导出 Oracle 数据
expdp system/密码@XE schemas=suzao directory=DATA_PUMP_DIR dumpfile=suzao.dmp

# 2. 通过 biancheng 中转
scp gong3@10.10.10.111:D:\\suzao.dmp /tmp/

# 3. ora2pg 转换
ora2pg -c /etc/ora2pg/ora2pg.conf -t TABLE -o suzao.sql
# 导入 PostgreSQL
psql -h 127.0.0.1 -U suzao -d suzao_db -f suzao.sql
```

### 第5步：验证（所有人）

```bash
# 编译 + 启动测试
cd /home/gong3/develop
go build -o suzao-server
# 或 mvn clean package

# 系统自检：连接新PG库跑业务功能
# 比对 Oracle 和 PG 数据一致性
```

## 四、分工

| 角色 | 职责 |
|------|------|
| **服务器技术** | 装PG+pgvector、配环境、数据导出传输 |
| **我（chaogu-ai）** | 方案统筹、SQL语法转换指导、验证检查 |
| **gong3** | Go/Java 数据库驱动适配 |
| **高级工程师** | 代码改造主力 + 数据迁移执行 |

## 五、时间线

```
本周: 环境搭建 (Day1-2) → 源码拉取 (Day3) → 开始适配 (Day4-5)
下周: 代码+数据迁移 → 并行验证
后续: 稳定运行后，等老板决定切换时间
```

## 六、风险

| 风险 | 等级 | 对策 |
|------|------|------|
| Oracle存储过程/函数 | 🟡 | PL/SQL → PL/pgSQL 语法接近 |
| 数据类型兼容 | 🟢 | NUMBER→NUMERIC, VARCHAR2→VARCHAR 自动转换 |
| 字符集问题 | 🟢 | PG 默认 UTF8 |
| 性能差异 | 🟡 | 上线前压测，调 PG 参数（shared_buffers, work_mem） |
| 数据丢失 | 🔴 | 仅在开发站操作，生产站不动 |

## 七、红线

```
❌ 未经老板命令，不得动生产站（goxeou.12fz.com）
✅ 一切操作在开发站（111）和 新环境（202）进行
