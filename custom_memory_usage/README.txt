custom_memory_usage - Zabbix 6.4 dashboard widget

功能：
- 選擇一個 Host group
- 顯示群組內主機名稱、Agent IP、已使用與總記憶體
- 依記憶體使用率由高至低排序

資料來源：
- vm.memory.size[pused]
- vm.memory.size[total]
- vm.memory.size[used]
- 支援 available 與 pavailable fallback
