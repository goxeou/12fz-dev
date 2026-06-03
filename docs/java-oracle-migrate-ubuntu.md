# Java + Oracle 从 Windows 迁移到 Ubuntu 方案

> 汇总时间：2026-06-01 14:30 CST
> 讨论参与：@chaogu-ai @服务器技术 @gong3（已确认共识）

---

## 一、当前架构（已完成迁移部分）

### ✅ 已在 Ubuntu 运行的核心组件

| 组件 | 运行方式 | 位置 |
|------|---------|------|
| **Oracle XE 21c** | Docker 容器 `oracle-xe` | 阿里云 8.138.235.183 |
| **Oracle 数据** | 绑定挂载 `/mnt/data/SHUZAO.dmp` | 阿里云 |
| **Java 应用** | systemd 服务 `shuzao-java.service` | 阿里云 |
| **JDK 11** | 本地安装 `/mnt/data/jdk-11/` | 阿里云 |
| **配置文件** | `application-prod.yml` | 阿里云 |

### 🟡 Windows 机器 (10.10.10.100) 仍在运行的服务

| 服务 | NPS 端口 | 说明 |
|------|---------|------|
| 加密软件 | 20080 / 20081 | 待迁移或保留 |
| qiuming RDP | 20100 → 3389 | 管理通道，可保留 |

---

## 二、迁移方案

### 阶段一：确认 Ubuntu 端就绪（优先级最高）

```
1. 解锁 Oracle system 账号
   docker exec oracle-xe sqlplus / as sysdba
   ALTER USER system ACCOUNT UNLOCK;
   ALTER USER system IDENTIFIED BY Cx99w06020354;

2. 验证 Java 应用连接 Oracle
   检查 application-prod.yml 中的连接配置
   检查 catalina.out 日志确保应用正常启动

3. 配置 Oracle 数据定时备份
   每天凌晨 3:00 自动 expdp 全库导出
   保留最近 7 天备份
```

### 阶段二：数据安全加固（上线前）

```
1. Oracle 数据卷持久化
   当前: 绑定挂载 /mnt/data/SHUZAO.dmp
   建议: Docker volume 或独立数据目录

2. 应用日志轮转
   当前 catalina.out 已 83MB 且持续增长
   建议: logrotate 配置自动切割

3. systemd 服务完善
   当前: 已配置 After=docker.service redis-server.service
   建议: 添加定时健康检查脚本
```

### 阶段三：Windows 下线（可选）

```
Windows 10.10.10.100 的加密软件和 RDP 通道
建议保留 NPS 隧道，Windows 暂不下线
等 Ubuntu 端稳定运行 1 周后再评估
```

---

## 三、关键技术细节

### Oracle 容器配置
```
镜像: gvenzl/oracle-xe:21.3.0-slim-faststart
端口: 1521
数据: /mnt/data/SHUZAO.dmp → /opt/oracle/dmp/
SID:  XE
```

### Java 应用配置
```
JDK:    OpenJDK 11
JAR:    shuzao.v65.7.jar (177MB)
启动:   spring.profiles.active=prod
配置:   application-prod.yml
内存:   当前 1.3G 峰值
```

### 服务依赖链
```
docker.service → oracle-xe 容器 → shuzao-java.service
redis-server → shuzao-java.service
```

---

## 四、风险清单

| 风险 | 等级 | 应对措施 |
|------|------|---------|
| Oracle 账号锁定 | 🔴 已存在 | 立即执行阶段一第一步 |
| 数据仅单副本 | 🟡 | 配置每日备份 |
| 日志无限增长 | 🟡 | 配置 logrotate |
| Windows 依赖 | 🟢 | 暂保留 Windows |
