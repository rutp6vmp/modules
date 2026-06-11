<?php

namespace Modules\CustomIcmpStatus\Actions;

use API;
use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

    protected function doAction(): void {
        $groupids = $this->fields_values['groupids'] ?? [];
        $hosts = [];
        $icmp = [];
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
                'search' => ['key_' => 'icmpping'],
                'sortfield' => 'key_'
            ]);

            foreach ($items as $item) {
                if (!preg_match(
                    '/^(icmpping|icmppingloss|icmppingsec)(?:\[.*\])?$/',
                    $item['key_'],
                    $matches
                )) {
                    continue;
                }

                $hostid = $item['hostid'];
                $metric = $matches[1];
                $priority = $item['key_'] === $metric ? 0 : 1;

                if (
                    !isset($icmp[$hostid][$metric])
                    || $priority < $icmp[$hostid][$metric]['priority']
                    || (
                        $priority === $icmp[$hostid][$metric]['priority']
                        && (int) $item['lastclock'] > $icmp[$hostid][$metric]['lastclock']
                    )
                ) {
                    $icmp[$hostid][$metric] = [
                        'value' => $item['lastvalue'],
                        'lastclock' => (int) $item['lastclock'],
                        'priority' => $priority
                    ];
                }
            }
        }

        foreach ($hosts as $host) {
            $hostid = $host['hostid'];
            $metrics = $icmp[$hostid] ?? [];
            $ping = $this->valueOf($metrics, 'icmpping');
            $loss = $this->valueOf($metrics, 'icmppingloss');
            $seconds = $this->valueOf($metrics, 'icmppingsec');
            $available = is_numeric($ping) ? (float) $ping > 0 : null;

            if ($available === null && is_numeric($loss)) {
                $available = (float) $loss < 100;
            }

            $rows[] = [
                'host_name' => $host['name'],
                'host_ip' => $this->getMainAddress($host['interfaces'] ?? []),
                'available' => $available,
                'latency_ms' => is_numeric($seconds) ? (float) $seconds * 1000 : null,
                'loss' => is_numeric($loss) ? max(0, min(100, (float) $loss)) : null,
                'message' => $available === null ? '尚無 ICMP 資料' : ''
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $a_rank = $a['available'] === false ? 0 : ($a['available'] === null ? 2 : 1);
            $b_rank = $b['available'] === false ? 0 : ($b['available'] === null ? 2 : 1);

            if ($a_rank !== $b_rank) {
                return $a_rank <=> $b_rank;
            }

            if (is_numeric($a['loss']) && is_numeric($b['loss'])) {
                $compare = (float) $b['loss'] <=> (float) $a['loss'];
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

    private function getMainAddress(array $interfaces): string {
        $fallback = '-';

        foreach ($interfaces as $interface) {
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
