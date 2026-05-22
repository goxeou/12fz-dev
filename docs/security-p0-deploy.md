# 12FZ 安全部署脚本

## 1. Fail2ban 安装

### Ubuntu/Debian (ESXi/gong3)
```bash
apt update && apt install -y fail2ban
```

### CentOS 7 (阿里云/服务器技术)
```bash
yum install -y epel-release
yum install -y fail2ban
```

### 配置SSH防护（通用）
```bash
cat > /etc/fail2ban/jail.d/sshd-local.conf << 'EOF'
[sshd]
enabled = true
port = 22
filter = sshd
logpath = /var/log/auth.log
maxretry = 5
bantime = 3600

[sshd-ddos]
enabled = true
port = 22
filter = sshd-ddos
logpath = /var/log/auth.log
maxretry = 3
bantime = 86400
EOF

# CentOS用 /var/log/secure 替换 /var/log/auth.log
systemctl restart fail2ban
fail2ban-client status
```

## 2. SSH密钥登录

### 生成密钥对（每个服务器独立生成）
```bash
ssh-keygen -t ed25519 -a 100 -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

### 测试密钥登录（本地测试）
```bash
ssh localhost -i ~/.ssh/id_ed25519
```

### 禁用密码登录（确认密钥可用后再执行！）
```bash
# 先确保密钥能正常登录，再执行下面
sed -i 's/^PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/^#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd
```

## 3. Nginx limit_req限速（阿里云）

在 Nginx 配置中添加：
```nginx
# 限制每个IP每秒最多10个请求
limit_req_zone $binary_remote_addr zone=login:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=wpapi:10m rate=30r/s;

server {
    # WP登录页限速
    location /wp-login.php {
        limit_req zone=login burst=5 nodelay;
    }
    # WP REST API限速
    location /wp-json/ {
        limit_req zone=wpapi burst=15 nodelay;
    }
    # XML-RPC限速
    location /xmlrpc.php {
        limit_req zone=login burst=3 nodelay;
        deny all;
    }
}
```

## 4. Slither 合约安全扫描（ESXi/gong3）

```bash
pip3 install slither-analyzer

# 扫描合约
cd /home/qiuming/12fz-token
slither . --detect all --json slither-report.json

# 只看高危问题
slither . --detect reentrancy-eth,reentrancy-no-eth,tx-origin,unchecked-lowlevel,calls-loop
```
