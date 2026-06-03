# suzao 从 Oracle 迁移到 PostgreSQL 方案

> 决策：老板拍板 | 日期：2026-06-01
> 技术栈：Go + Java + PostgreSQL 18 + Ubuntu 26.04
> 目标机器：biancheng (10.10.10.202)

---

## 一、架构总览

### 当前（Windows 10.10.10.111）
```
D:\develop          → Go + Java 源码
D:\SzJavaClient     → Oracle XE (数据库)
```

### 目标（Ubuntu 10.10.10.202 biancheng）
```
/home/gong3/develop → Go + Java 源码
PostgreSQL 18       → 数据库（含 pgvector 扩展）
```

---

## 二、迁移步骤

### 阶段一：环境准备（服务器技术）

```
1. 在 biancheng (10.10.10.202) 上安装基础环境
   sudo apt update
   sudo apt install -y postgresql postgresql-contrib golang-go openjdk-17-jdk git maven
   
2. 安装 pgvector（AI 向量扩展）
   sudo apt install -y postgresql-18-pgvector
   
3. 配置 PostgreSQL
   sudo -u postgres psql -c "CREATE USER suzao WITH PASSWORD 'Cx99w06020354';"
   sudo -u postgres psql -c "CREATE DATABASE suzao_db OWNER suzao;"
   sudo -u postgres psql -c "CREATE EXTENSION vector;"
   
4. 配置远程访问（让Java/Go能连）
   修改 /etc/postgresql/18/main/pg_hba.conf
   增加: host    suzao_db    suzao    10.10.10.0/24    md5
```

### 阶段二：代码迁移（我 + 服务器技术配合）

```
1. 从 111 获取源码
   scp -r gong3@10.10.10.111:D:\develop /home/gong3/
   
2. 确认语言占比（Go vs Java）
   find /home/gong3/develop -name "*.go" | wc -l
   find /home/gong3/develop -name "*.java" | wc -l
   
3. 数据库驱动改造
   Java:  ojdbc → postgresql JDBC driver
   Go:    ？    → github.com/jackc/pgx/v5
   
4. SQL 语法改造
   - SEQUENCE → SERIAL/BIGSERIAL
   - NVL() → COALESCE()
   - SYSDATE → NOW()
   - || 字符串连接 → 兼容（PG也支持||）
   - ROWNUM → LIMIT
   - MERGE INTO → INSERT ... ON CONFLICT
```

### 阶段三：数据迁移

```
1. 在 111 上导出 Oracle 数据
   expdp system/xxx@XE schemas=suzao directory=DATA_PUMP_DIR dumpfile=suzao.dmp
   
2. 传送到 Ubuntu
   通过 202 中转: scp → scp
   
3. 使用 ora2pg 工具自动转换
   sudo apt install -y ora2pg
   ora2pg -c ora2pg.conf
   
   或使用 pgloader（更简单）
   pgloader mysql://... → postgresql://suzao@localhost/suzao_db
```

### 阶段四：部署上线

```
1. Go/Java 应用编译
   cd /home/gong3/develop
   go build -o suzao-server
   mvn clean package
   
2. 创建 systemd 服务
   /etc/systemd/system/suzao.service
   
3. 验证
   - 业务功能测试
   - 数据完整性校验
   - 性能基准
```

---

## 三、分工

| 角色 | 职责 |
|------|------|
| **服务器技术** | 环境安装、数据导出、文件传输、postgresql配置 |
| **我（chaogu-ai）** | SQL语法转换指导、代码改造检查、部署验证 |
| **gong3** | Go/Java 代码的数据库驱动适配 |

---

## 四、风险

| 风险 | 等级 | 对策 |
|------|------|------|
| Oracle存储过程/函数 | 🟡 | 改为PG的PL/pgSQL，语法接近 |
| 字符集差异 | 🟢 | PG用UTF8即可 |
| 数据类型不兼容 | 🟢 | NUMBER→NUMERIC, VARCHAR2→VARCHAR |
| 性能下降 | 🟡 | 上线前做压测，调PG参数 |
