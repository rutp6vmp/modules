custom_cpu_usage - Zabbix 6.4 dashboard widget

功能：
- 選擇一個 Host group
- 顯示群組內主機名稱、Agent IP 與 CPU 使用率
- 依 CPU 使用率由高至低排序

資料來源：
- system.cpu.util[,idle]
- 若模板使用 avg1 或其他 idle 變體，也會自動選取

使用率換算：100 - idle
