<?php

namespace Modules\CustomCpuUsage\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

    protected function doAction(): void {
        $groupids = $this->fields_values['groupids'] ?? [];
        $hosts = [];
        $cpu_items = [];
        $rows = [];

        if ($groupids) {
            $hosts = API::Host()->get([
                'output' => ['hostid', 'name'],
                'selectInterfaces' => [
                    'ip', 'dns', 'useip', 'type', 'main'
                ],
                'groupids' => $groupids,
                'monitored_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => ZBX_SORT_UP
            ]);

            $items = API::Item()->get([
                'output' => ['hostid', 'key_', 'lastvalue', 'lastclock'],
                'groupids' => $groupids,
                'monitored' => true,
                'search' => ['key_' => 'system.cpu.util'],
                'sortfield' => 'key_'
            ]);

            foreach ($items as $item) {
                if (!preg_match(
                    '/^system\.cpu\.util\[[^,]*,idle(?:,[^,\]]*){0,2}\]$/',
                    $item['key_']
                )) {
                    continue;
                }

                $priority = $item['key_'] === 'system.cpu.util[,idle]'
                    ? 0
                    : ($item['key_'] === 'system.cpu.util[,idle,avg1]' ? 1 : 2);
                $hostid = $item['hostid'];

                if (
                    !isset($cpu_items[$hostid])
                    || $priority < $cpu_items[$hostid]['priority']
                    || (
                        $priority === $cpu_items[$hostid]['priority']
                        && (int) $item['lastclock'] > $cpu_items[$hostid]['lastclock']
                    )
                ) {
                    $cpu_items[$hostid] = [
                        'idle' => $item['lastvalue'],
                        'lastclock' => (int) $item['lastclock'],
                        'priority' => $priority
                    ];
                }
            }
        }

        foreach ($hosts as $host) {
            $hostid = $host['hostid'];
            $host_ip = $this->getAgentAddress($host['interfaces'] ?? []);
            $usage = null;

            if (isset($cpu_items[$hostid]) && is_numeric($cpu_items[$hostid]['idle'])) {
                $usage = max(0, min(100, 100 - (float) $cpu_items[$hostid]['idle']));
            }

            $rows[] = [
                'host_name' => $host['name'],
                'host_ip' => $host_ip,
                'usage' => $usage,
                'message' => $usage === null ? '尚無 CPU 資料' : ''
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if (is_numeric($a['usage']) !== is_numeric($b['usage'])) {
                return is_numeric($a['usage']) ? -1 : 1;
            }

            if (is_numeric($a['usage']) && is_numeric($b['usage'])) {
                $compare = (float) $b['usage'] <=> (float) $a['usage'];
                if ($compare !== 0) {
                    return $compare;
                }
            }

            return strcasecmp($a['host_name'], $b['host_name']);
        });

        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'rows' => $rows,
            'user' => ['debug_mode' => $this->getDebugMode()]
        ]));
    }

    private function getAgentAddress(array $interfaces): string {
        $fallback = '-';

        foreach ($interfaces as $interface) {
            if ((int) $interface['type'] !== 1) {
                continue;
            }

            $address = (int) $interface['useip'] === 1
                ? $interface['ip']
                : $interface['dns'];

            if ($address === '') {
                continue;
            }

            if ($fallback === '-') {
                $fallback = $address;
            }

            if ((int) $interface['main'] === 1) {
                return $address;
            }
        }

        return $fallback;
    }
}
