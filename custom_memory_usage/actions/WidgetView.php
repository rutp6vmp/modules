<?php

namespace Modules\CustomMemoryUsage\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

    protected function doAction(): void {
        $groupids = $this->fields_values['groupids'] ?? [];
        $hosts = [];
        $memory = [];
        $rows = [];

        if ($groupids) {
            $hosts = API::Host()->get([
                'output' => ['hostid', 'name'],
                'selectInterfaces' => ['ip', 'dns', 'useip', 'type', 'main'],
                'groupids' => $groupids,
                'monitored_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => ZBX_SORT_UP
            ]);

            $items = API::Item()->get([
                'output' => ['hostid', 'key_', 'lastvalue', 'lastclock'],
                'groupids' => $groupids,
                'monitored' => true,
                'search' => ['key_' => 'vm.memory.size'],
                'sortfield' => 'key_'
            ]);

            foreach ($items as $item) {
                if (!preg_match(
                    '/^vm\.memory\.size\[(total|used|available|pused|pavailable)\]$/',
                    $item['key_'],
                    $matches
                )) {
                    continue;
                }

                $hostid = $item['hostid'];
                $metric = $matches[1];

                if (
                    !isset($memory[$hostid][$metric])
                    || (int) $item['lastclock'] > $memory[$hostid][$metric]['lastclock']
                ) {
                    $memory[$hostid][$metric] = [
                        'value' => $item['lastvalue'],
                        'lastclock' => (int) $item['lastclock']
                    ];
                }
            }
        }

        foreach ($hosts as $host) {
            $hostid = $host['hostid'];
            $metrics = $memory[$hostid] ?? [];
            $total = $this->valueOf($metrics, 'total');
            $used = $this->valueOf($metrics, 'used');
            $available = $this->valueOf($metrics, 'available');
            $usage = $this->valueOf($metrics, 'pused');
            $pavailable = $this->valueOf($metrics, 'pavailable');

            if (!is_numeric($used) && is_numeric($total) && is_numeric($available)) {
                $used = max(0, (float) $total - (float) $available);
            }

            if (!is_numeric($usage) && is_numeric($pavailable)) {
                $usage = 100 - (float) $pavailable;
            }
            elseif (
                !is_numeric($usage)
                && is_numeric($used)
                && is_numeric($total)
                && (float) $total > 0
            ) {
                $usage = (float) $used / (float) $total * 100;
            }

            if (is_numeric($usage)) {
                $usage = max(0, min(100, (float) $usage));
            }

            $rows[] = [
                'host_name' => $host['name'],
                'host_ip' => $this->getAgentAddress($host['interfaces'] ?? []),
                'total' => $total,
                'used' => $used,
                'usage' => $usage,
                'message' => is_numeric($usage) ? '' : '尚無記憶體資料'
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

    private function valueOf(array $metrics, string $name) {
        return isset($metrics[$name]) ? $metrics[$name]['value'] : null;
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
