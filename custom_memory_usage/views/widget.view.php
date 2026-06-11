<?php

/** @var CView $this */
/** @var array $data */

$format_bytes = static function ($bytes): string {
    if (!is_numeric($bytes)) {
        return '-';
    }

    $value = (float) $bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $index = 0;

    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }

    return number_format($value, 1).' '.$units[$index];
};

$format_percent = static function ($value): string {
    return is_numeric($value) ? number_format((float) $value, 1).'%' : '-';
};

$create_progress = static function ($value) use ($format_percent): CTag {
    $numeric = is_numeric($value) ? max(0, min(100, (float) $value)) : 0;
    $severity = $numeric >= 90
        ? 'metric-progress__fill--critical'
        : ($numeric >= 80 ? 'metric-progress__fill--warning' : 'metric-progress__fill--normal');
    $bar = (new CTag('div', true))
        ->addClass('metric-progress')
        ->setAttribute('title', $format_percent($value));
    $bar->addItem(
        (new CTag('div', true))
            ->addClass('metric-progress__fill')
            ->addClass($severity)
            ->setAttribute('style', 'width: '.number_format($numeric, 2, '.', '').'%;')
    );
    $bar->addItem(
        (new CTag('span', true, $format_percent($value)))
            ->addClass('metric-progress__text')
    );
    return $bar;
};

$table = (new CTableInfo())->setHeader([
    _('電腦名稱'),
    _('IP 位址'),
    _('已使用記憶體'),
    _('總記憶體'),
    _('使用率')
]);

foreach ($data['rows'] as $row) {
    $table->addRow([
        $row['host_name'],
        $row['host_ip'],
        $format_bytes($row['used']),
        $format_bytes($row['total']),
        is_numeric($row['usage']) ? $create_progress($row['usage']) : $row['message']
    ]);
}

(new CWidgetView($data))->addItem($table)->show();
