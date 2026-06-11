<?php

/** @var CView $this */
/** @var array $data */

$format_latency = static function ($value): string {
    if (!is_numeric($value)) {
        return '-';
    }

    $value = (float) $value;
    return number_format($value, $value < 10 ? 2 : 1).' ms';
};

$format_loss = static function ($value): string {
    return is_numeric($value) ? number_format((float) $value, 1).'%' : '-';
};

$create_status = static function ($available, string $message): CTag {
    if ($available === true) {
        $text = '正常';
        $class = 'icmp-status--up';
    }
    elseif ($available === false) {
        $text = '無回應';
        $class = 'icmp-status--down';
    }
    else {
        $text = $message;
        $class = 'icmp-status--unknown';
    }

    return (new CTag('span', true, $text))
        ->addClass('icmp-status')
        ->addClass($class);
};

$table = (new CTableInfo())->setHeader([
    _('電腦名稱'),
    _('IP 位址'),
    _('連線狀態'),
    _('檢查來源'),
    _('延遲'),
    _('封包遺失率')
]);

foreach ($data['rows'] as $row) {
    $loss_class = is_numeric($row['loss']) && (float) $row['loss'] > 20
        ? 'icmp-value--critical'
        : (is_numeric($row['loss']) && (float) $row['loss'] > 0 ? 'icmp-value--warning' : '');
    $loss = (new CTag('span', true, $format_loss($row['loss'])))->addClass($loss_class);

    $table->addRow([
        $row['host_name'],
        $row['host_ip'],
        $create_status($row['available'], $row['message']),
        $row['source'],
        $format_latency($row['latency_ms']),
        $loss
    ]);
}

(new CWidgetView($data))->addItem($table)->show();
