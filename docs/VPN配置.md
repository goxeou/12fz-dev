# xray REALITY VPN 配置信息（Vultr日本）

> 服务器: `web.goxeou.com:8443`

---

## 服务端（Vultr日本 167.179.79.44）

| 参数 | 值 |
|------|-----|
| 域名 | `web.goxeou.com:8443` |
| 协议 | VLESS + XTLS + REALITY |
| 伪装目标 | `www.microsoft.com:443` |
| Fingerprint | `chrome` |

### 密钥

| 参数 | 值 |
|------|-----|
| PrivateKey | `uFTtp5hFwQdwc776kLU21cOwKKBgt7P3yc8XrCrZ7Wk` |
| PublicKey | `1ia-5tTtVO9YjM62yldipd9acyinKv8M8b1-f1cG9mc` |
| shortId | `993b07dae6acea9f` |

### 客户端UUID

| 客户端 | UUID |
|--------|------|
| ESXi/gong3 服务器 | `de56b709-a55a-580e-8a72-f80f4338bd37` |
| iPhone 手机 | `c597e0b7-7184-5da1-a5ef-645b308100cd` |

---

## 客户端配置

### iPhone Shadowrocket 一键导入链接
```
vless://c597e0b7-7184-5da1-a5ef-645b308100cd@web.goxeou.com:8443?encryption=none&flow=xtls-rprx-vision&security=reality&sni=www.microsoft.com&fp=chrome&pbk=1ia-5tTtVO9YjM62yldipd9acyinKv8M8b1-f1cG9mc&sid=993b07dae6acea9f&type=tcp&headerType=none#Vultr
```

### iPhone Shadowrocket 手动配置

| 字段 | 值 |
|------|-----|
| 类型 | **VLESS** |
| 地址 | `web.goxeou.com` |
| 端口 | `8443` |
| UUID | `c597e0b7-7184-5da1-a5ef-645b308100cd` |
| Encryption | `none` |
| Flow | `xtls-rprx-vision` |
| Reality ON | ✅ |
| PublicKey | `1ia-5tTtVO9YjM62yldipd9acyinKv8M8b1-f1cG9mc` |
| ShortId | `993b07dae6acea9f` |
| ServerName | `www.microsoft.com` |
| Fingerprint | `chrome` |

### Linux/ESXi 客户端 config.json

```json
{
  "log": {"loglevel": "warning"},
  "inbounds": [
    {"port": 10808, "protocol": "socks", "settings": {"auth": "noauth", "udp": true}, "listen": "127.0.0.1"},
    {"port": 10809, "protocol": "http", "settings": {"auth": "noauth"}, "listen": "127.0.0.1"}
  ],
  "outbounds": [{
    "protocol": "vless",
    "tag": "proxy",
    "settings": {
      "vnext": [{
        "address": "web.goxeou.com",
        "port": 8443,
        "users": [{"id": "de56b709-a55a-580e-8a72-f80f4338bd37", "flow": "xtls-rprx-vision", "encryption": "none"}]
      }]
    },
    "streamSettings": {
      "network": "tcp",
      "security": "reality",
      "realitySettings": {
        "serverName": "www.microsoft.com",
        "fingerprint": "chrome",
        "publicKey": "1ia-5tTtVO9YjM62yldipd9acyinKv8M8b1-f1cG9mc",
        "shortId": "993b07dae6acea9f"
      }
    }
  }]
}
```
