# gong3 ESXi xray配置文件

放在 /usr/local/etc/xray/config.json

```json
{
  "log": {
    "loglevel": "warning"
  },
  "inbounds": [
    {
      "tag": "socks-in",
      "port": 10808,
      "listen": "127.0.0.1",
      "protocol": "socks",
      "settings": {
        "auth": "noauth",
        "udp": true
      }
    },
    {
      "tag": "http-in",
      "port": 10809,
      "listen": "127.0.0.1",
      "protocol": "http"
    }
  ],
  "outbounds": [
    {
      "tag": "proxy",
      "protocol": "vless",
      "settings": {
        "vnext": [
          {
            "address": "167.179.79.44",
            "port": 8443,
            "users": [
              {
                "id": "c597e0b7-7184-5da1-a5ef-645b308100cd",
                "flow": "xtls-rprx-vision",
                "encryption": "none"
              }
            ]
          }
        ]
      },
      "streamSettings": {
        "network": "tcp",
        "security": "reality",
        "realitySettings": {
          "serverName": "www.microsoft.com",
          "fingerprint": "random",
          "publicKey": "1ia-5tTtVO9YjM62yldipd9acyinKv8M8b1-f1cG9mc",
          "shortId": "993b07dae6acea9f"
        }
      }
    },
    {
      "tag": "direct",
      "protocol": "freedom"
    }
  ],
  "routing": {
    "rules": [
      {
        "type": "field",
        "domain": [
          "geosite:cn",
          "domain:api.deepseek.com",
          "domain:deepseek.com",
          "domain:open.feishu.cn",
          "domain:dashscope.aliyuncs.com"
        ],
        "outboundTag": "direct"
      },
      {
        "type": "field",
        "inboundTag": ["socks-in", "http-in"],
        "outboundTag": "proxy"
      }
    ]
  }
}
```

## 安装步骤

```bash
# 1. 安装xray（如果未装）
bash -c "$(curl -L https://github.com/XTLS/Xray-install/raw/main/install-release.sh)" @ install

# 2. 替换配置文件
sudo cp config.json /usr/local/etc/xray/config.json

# 3. 启动xray
sudo systemctl restart xray
sudo systemctl enable xray

# 4. 测试
curl -sx http://127.0.0.1:10809 --connect-timeout 10 https://www.google.com -o /dev/null -w '%{http_code}'
# 期望输出: 200

# 5. 设置环境变量（加到 ~/.bashrc）
export http_proxy=http://127.0.0.1:10809
export https_proxy=http://127.0.0.1:10809
# 国内地址不走代理
export no_proxy=localhost,127.0.0.1,*.feishu.cn,*.deepseek.com,*.aliyuncs.com,*.baidu.com,*.12fz.com
```

## 路由规则

| 目标 | 走哪 | 说明 |
|------|------|------|
| 国内网站（geosite:cn） | direct ✅ 直连 | 百度/阿里云等 |
| DeepSeek API | direct ✅ 直连 | 翻墙反而慢 |
| 飞书 | direct ✅ 直连 | 国内服务 |
| 其他 | proxy → Vultr 8443 | GitHub/Google/npm等 |
