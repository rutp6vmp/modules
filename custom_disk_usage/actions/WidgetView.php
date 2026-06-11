<?php

namespace Modules\CustomDiskUsage\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

    protected function doAction(): void {
        $groupids = $this->fields_values['groupids'] ?? [];

        $hosts = [];
        $items = [];
        $disks = [];
        $rows = [];

        /*
         * 取得所選 Host group 底下所有啟用監控的主機。
         *
         * selectInterfaces 用來取得 Agent Interface IP。
         */
        if ($groupids) {
            $hosts = API::Host()->get([
                'output' => [
                    'hostid',
                    'host',
                    'name'
                ],
                'selectInterfaces' => [
                    'interfaceid',
                    'ip',
                    'dns',
                    'useip',
                    'type',
                    'main'
                ],
                'groupids' => $groupids,
                'monitored_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => ZBX_SORT_UP
            ]);

            /*
             * 一次取得群組內所有檔案系統容量 Item。
             *
             * 支援格式：
             *
             * Windows：
             *   vfs.fs.size[C:,total]
             *   vfs.fs.size[C:,used]
             *   vfs.fs.size[C:,pused]
             *
             * Linux：
             *   vfs.fs.size[/,total]
             *   vfs.fs.size[/,used]
             *   vfs.fs.size[/,pused]
             *
             * 新版模板的 dependent item：
             *   vfs.fs.dependent.size[C:,total]
             *   vfs.fs.dependent.size[/,pused]
             */
            $items = API::Item()->get([
                'output' => [
                    'hostid',
                    'key_',
                    'lastvalue',
                    'lastclock'
                ],
                'groupids' => $groupids,
                'monitored' => true,
                'search' => [
                    'key_' => 'vfs.fs.'
                ],
                'sortfield' => 'key_'
            ]);
        }

        /*
         * 將同一個磁碟槽或掛載點的 total、used、pused 整理到同一筆資料。
         */
        foreach ($items as $item) {
            if (!preg_match(
                '/^vfs\.fs(?:\.dependent)?\.size\[(.+),(total|used|pused)\]$/',
                $item['key_'],
                $matches
            )) {
                continue;
            }

            $hostid = $item['hostid'];
            $filesystem = $matches[1];
            $metric = $matches[2];

            if (!isset($disks[$hostid][$filesystem])) {
                $disks[$hostid][$filesystem] = [
                    'total' => null,
                    'used' => null,
                    'pused' => null,
                    'lastclock' => 0
                ];
            }

            $disks[$hostid][$filesystem][$metric] = $item['lastvalue'];

            /*
             * 暫時保留最後更新時間，未來可用來判斷資料是否過期。
             * View 不會顯示此欄位。
             */
            $disks[$hostid][$filesystem]['lastclock'] = max(
                $disks[$hostid][$filesystem]['lastclock'],
                (int) $item['lastclock']
            );
        }

        /*
         * 建立每台主機的表格資料。
         */
        foreach ($hosts as $host) {
            $hostid = $host['hostid'];

            /*
             * 取得主要 Agent Interface IP。
             *
             * Interface type：
             *   1 = Zabbix Agent
             *   2 = SNMP
             *   3 = IPMI
             *   4 = JMX
             *
             * 優先使用 main = 1 的 Agent Interface。
             */
            $host_ip = '-';
            $fallback_ip = '-';

            foreach ($host['interfaces'] ?? [] as $interface) {
                if ((int) $interface['type'] !== 1) {
                    continue;
                }

                $interface_address = (int) $interface['useip'] === 1
                    ? $interface['ip']
                    : $interface['dns'];

                if ($interface_address === '') {
                    continue;
                }

                if ($fallback_ip === '-') {
                    $fallback_ip = $interface_address;
                }

                if ((int) $interface['main'] === 1) {
                    $host_ip = $interface_address;
                    break;
                }
            }

            if ($host_ip === '-') {
                $host_ip = $fallback_ip;
            }

            /*
             * 主機尚未取得磁碟資料時，仍保留一列提示。
             */
            if (empty($disks[$hostid])) {
                $rows[] = [
                    'host_name' => $host['name'],
                    'host_ip' => $host_ip,
                    'hostid' => $hostid,
                    'filesystem' => null,
                    'is_linux' => false,
                    'total' => null,
                    'used' => null,
                    'pused' => null,
                    'lastclock' => 0,
                    'message' => '尚無磁碟資料'
                ];

                continue;
            }

            foreach ($disks[$hostid] as $filesystem => $disk) {
                /*
                 * 如果模板沒有 pused，使用 used / total 自動計算。
                 */
                if (
                    ($disk['pused'] === null || $disk['pused'] === '')
                    && is_numeric($disk['used'])
                    && is_numeric($disk['total'])
                    && (float) $disk['total'] > 0
                ) {
                    $disk['pused'] = (
                        (float) $disk['used']
                        / (float) $disk['total']
                    ) * 100;
                }

                $rows[] = [
                    'host_name' => $host['name'],
                    'host_ip' => $host_ip,
                    'hostid' => $hostid,
                    'filesystem' => $filesystem,
                    'is_linux' => isset($filesystem[0]) && $filesystem[0] === '/',
                    'total' => $disk['total'],
                    'used' => $disk['used'],
                    'pused' => $disk['pused'],
                    'lastclock' => $disk['lastclock'],
                    'message' => ''
                ];
            }
        }

        /*
         * 動態排序：
         *
         * 1. 有使用率資料的磁碟優先顯示。
         * 2. 使用率較高的磁碟排在最上方。
         * 3. 使用率相同時，依主機名稱排序。
         * 4. 同一台主機內，依磁碟槽或掛載點自然排序。
         * 5. 沒有磁碟資料的主機排在最下方。
         */
        usort($rows, static function (array $a, array $b): int {
            $a_has_value = is_numeric($a['pused']);
            $b_has_value = is_numeric($b['pused']);

            if ($a_has_value && !$b_has_value) {
                return -1;
            }

            if (!$a_has_value && $b_has_value) {
                return 1;
            }

            if ($a_has_value && $b_has_value) {
                $usage_compare = (float) $b['pused'] <=> (float) $a['pused'];

                if ($usage_compare !== 0) {
                    return $usage_compare;
                }
            }

            $host_compare = strcasecmp(
                (string) $a['host_name'],
                (string) $b['host_name']
            );

            if ($host_compare !== 0) {
                return $host_compare;
            }

            return strnatcasecmp(
                (string) $a['filesystem'],
                (string) $b['filesystem']
            );
        });

        $this->setResponse(
            new CControllerResponseData([
                'name' => $this->getInput(
                    'name',
                    $this->widget->getName()
                ),
                'rows' => $rows,
                'user' => [
                    'debug_mode' => $this->getDebugMode()
                ]
            ])
        );
    }
}
