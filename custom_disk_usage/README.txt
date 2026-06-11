custom_disk_usage - Zabbix 6.4 dashboard widget

功能：
- 可選擇一台 Zabbix Host
- 顯示 Host 名稱與 IP
- 顯示各硬碟掛載點、總空間、已使用空間、使用率
- Linux 掛載點的容量依 df -h 原則顯示
- 不顯示最後更新時間

安裝：
1. 將 custom_disk_usage.tar.gz 上傳到 Zabbix 伺服器。
2. 解壓縮至 /usr/share/zabbix/modules。
3. 到 Zabbix 網頁：Administration -> General -> Modules -> Scan directory。
4. 啟用「硬碟使用狀況」。
5. 儀表板新增 Widget，選擇「硬碟使用狀況」，再指定 Host。

資料來源：
- vfs.fs.size[掛載點,total]
- vfs.fs.size[掛載點,used]
- vfs.fs.size[掛載點,pused]
