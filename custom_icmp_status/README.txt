custom_icmp_status - Zabbix 6.4 dashboard widget

功能：
- 選擇一個 Host group
- 顯示群組內主機的連線狀態
- 優先使用 ICMP，沒有 ICMP item 時自動改用 agent.ping
- agent.ping 也不存在時，使用 Agent interface availability
- 無回應主機優先顯示

資料來源：
- icmpping
- icmppingsec
- icmppingloss
- agent.ping
- Agent interface availability

注意：
- ICMP 由 Zabbix server 或 proxy 執行，不需要 Agent 支援。
- Agent fallback 只能判斷 Agent 是否回應，不會提供 ICMP 延遲與封包遺失率。
