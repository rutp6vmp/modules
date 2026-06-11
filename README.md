# Zabbix Custom Dashboard Modules

適用於 Zabbix 6.4 的自訂 Dashboard Widget modules。

## Modules

- `custom_disk_usage`：磁碟容量與使用率，Linux 容量依 `df -h` 原則顯示
- `custom_cpu_usage`：CPU 使用率
- `custom_memory_usage`：記憶體容量與使用率
- `custom_icmp_status`：連線狀態；優先 ICMP，沒有 ICMP item 時改用 Agent

## 安裝

將需要的 module 目錄放到 `/usr/share/zabbix/modules/`，然後到 Zabbix：

`Administration -> General -> Modules -> Scan directory`

掃描後啟用 module，再從 Dashboard 新增對應 Widget 並選擇 Host group。

## 主要 Item Keys

- Disk：`vfs.fs.size[*]`
- CPU：`system.cpu.util[,idle]`
- Memory：`vm.memory.size[*]`
- Connectivity：`icmpping*`，fallback 為 `agent.ping`
