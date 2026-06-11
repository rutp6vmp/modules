<?php

/**
 * 磁碟空間狀態 Widget
 *
 * 顯示欄位：
 * - 電腦名稱
 * - IP 位址
 * - Windows 磁碟槽或 Linux 掛載點
 * - 已使用空間
 * - 總空間
 * - 使用率進度條
 *
 * @var CView $this
 * @var array $data
 */

/*
 * 將 bytes 轉換成易讀格式。
 *
 * 範例：
 *   1073741824 -> 1.0 GB
 */
$format_bytes = static function ($bytes): string {
    if ($bytes === null || $bytes === '' || !is_numeric($bytes)) {
        return '-';
    }

    $bytes = (float) $bytes;

    $units = [
        'B',
        'KB',
        'MB',
        'GB',
        'TB',
        'PB'
    ];

    $index = 0;

    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }

    return number_format($bytes, 1).' '.$units[$index];
};
/*
 * 依 GNU df -h 的方式顯示 Linux 檔案系統容量：
 * - 以 1024 為進位基準
 * - 使用 K、M、G、T、P 單位
 * - 小於 10 顯示一位小數，其餘顯示整數
 * - 為避免低估容量，顯示值一律向上取整
 *
 * 範例：
 *   1073741824 -> 1.0G
 *   10737418240 -> 10G
 */
$format_df_bytes = static function ($bytes): string {
    if ($bytes === null || $bytes === '' || !is_numeric($bytes)) {
        return '-';
    }

    $value = max(0, (float) $bytes);
    $units = [
        'B',
        'K',
        'M',
        'G',
        'T',
        'P'
    ];
    $index = 0;

    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }

    if ($index === 0) {
        return number_format(ceil($value), 0, '.', '');
    }

    $rounded_value = $value < 10
        ? ceil($value * 10) / 10
        : ceil($value);

    if ($rounded_value >= 1024 && $index < count($units) - 1) {
        $rounded_value /= 1024;
        $index++;
    }

    $decimals = $rounded_value < 10 ? 1 : 0;

    return number_format($rounded_value, $decimals, '.', '').$units[$index];
};

/*
 * 將使用率轉換成百分比格式。
 *
 * 範例：
 *   82.45321 -> 82.5%
 */
$format_percent = static function ($value): string {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '-';
    }

    return number_format((float) $value, 1).'%';
};

/*
 * 建立使用率進度條。
 *
 * 門檻：
 *   低於 80%       -> 綠色
 *   80% 至 89.9%   -> 黃色
 *   90% 以上       -> 紅色
 */
$create_progress_bar = static function ($value) use ($format_percent): CTag {
    $numeric_value = is_numeric($value)
        ? max(0, min(100, (float) $value))
        : 0;

    if ($numeric_value >= 90) {
        $severity_class = 'disk-progress__fill--critical';
    }
    elseif ($numeric_value >= 80) {
        $severity_class = 'disk-progress__fill--warning';
    }
    else {
        $severity_class = 'disk-progress__fill--normal';
    }

    $bar = (new CTag('div', true))
        ->addClass('disk-progress')
        ->setAttribute(
            'title',
            $format_percent($value)
        );

    $bar->addItem(
        (new CTag('div', true))
            ->addClass('disk-progress__fill')
            ->addClass($severity_class)
            ->setAttribute(
                'style',
                'width: '.number_format(
                    $numeric_value,
                    2,
                    '.',
                    ''
                ).'%;'
            )
    );

    $bar->addItem(
        (new CTag(
            'span',
            true,
            $format_percent($value)
        ))
            ->addClass('disk-progress__text')
    );

    return $bar;
};

/*
 * 建立表格。
 */
$table = (new CTableInfo())
    ->setHeader([
        _('電腦名稱'),
        _('IP 位址'),
        _('磁碟槽／掛載點'),
        _('已使用空間'),
        _('總空間'),
        _('使用率')
    ]);

/*
 * 加入資料列。
 */
foreach ($data['rows'] as $row) {
    /*
     * 尚無磁碟資料的主機。
     */
    if ($row['filesystem'] === null) {
        $table->addRow([
            $row['host_name'],
            $row['host_ip'],
            '-',
            '-',
            '-',
            $row['message']
        ]);

        continue;
    }

    /*
     * 正常磁碟資料。
     */
    $format_size = isset($row['is_linux']) && $row['is_linux']
        ? $format_df_bytes
        : $format_bytes;

    $table->addRow([
        $row['host_name'],
        $row['host_ip'],
        $row['filesystem'],
        $format_size($row['used']),
        $format_size($row['total']),
        $create_progress_bar($row['pused'])
    ]);
}

/*
 * 輸出 Widget。
 */
(new CWidgetView($data))
    ->addItem($table)
    ->show();
