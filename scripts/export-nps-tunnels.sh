#!/bin/bash
# 导出NPS隧道配置到可读格式
# 用法: ssh阿里云后执行
sshpass -p 'Cx99w06020354' ssh -o ConnectTimeout=60 -o StrictHostKeyChecking=no root@8.138.235.183 << 'PYEOF'
python3 << 'EOF'
import json
from collections import Counter

with open("/etc/nps/conf/tasks.json") as f:
    c = f.read()

entries = c.split("*#*")
tunnels = []
for e in entries:
    b = e.strip()
    if not b:
        continue
    try:
        d = json.loads(b)
        if "Port" not in d and "Client" in d:
            continue  # skip client entry
        tid = d.get("Id", 0)
        port = d.get("Port", 0)
        mode = d.get("Mode", "(空)")
        remark = d.get("Remark", "")
        status = "ON" if d.get("Status", False) else "OFF"
        target = d.get("Target", {}).get("TargetStr", "")
        password = d.get("Password", "")
        client_id = d.get("Client", {}).get("Id", 0)
        is_http = d.get("IsHttp", False)
        
        tunnels.append({
            "id": tid, "port": port, "mode": mode, "remark": remark,
            "status": status, "target": target, "password": password,
            "client": client_id, "is_http": is_http
        })
    except:
        pass

tunnels.sort(key=lambda x: x["port"] if x["port"] else 0)

print("=" * 90)
print("  NPS 隧道配置完整导出")
print("=" * 90)
print(f"{'ID':>4} {'端口':>5} {'模式':<8} {'状态':<3} {'备注':<20} {'目标地址':<22} {'密码':<10}")
print("-" * 90)
for t in tunnels:
    print(f"{t['id']:>4} {t['port']:>5} {t['mode']:<8} {t['status']:<3} {t['remark']:<20} {t['target']:<22} {t['password'] or '-':<10}")
print("-" * 90)
print(f"  共 {len(tunnels)} 条隧道")

# Find duplicates
print()
print("=" * 90)
print("  重复端口清单:")
ports = [t["port"] for t in tunnels if t["port"] > 0]
for p, cnt in sorted(Counter(ports).items()):
    if cnt > 1:
        dupes = [t for t in tunnels if t["port"] == p]
        modes = "/".join([t["mode"] for t in dupes])
        print(f"  端口 {p:>5} ({cnt}条): {' + '.join([f'#{t[\"id\"]} {t[\"mode\"]}' for t in dupes])}")
EOF
PYEOF