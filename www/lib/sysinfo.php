<?php
// SPDX-License-Identifier: MIT
// lib/sysinfo.php — 服务器状态采集（/proc + 磁盘），无需任何外部依赖
declare(strict_types=1);

function sys_cpu_sample(): array {
    $line = '';
    foreach (file('/proc/stat', FILE_IGNORE_NEW_LINES) ?: [] as $l) {
        if (str_starts_with($l, 'cpu ')) { $line = $l; break; }
    }
    $f = array_map('intval', array_slice(preg_split('/\s+/', trim($line)), 1));
    // user nice system idle iowait irq softirq steal
    $idle = $f[3] + ($f[4] ?? 0);
    $total = array_sum($f);
    return [$idle, $total];
}

function sys_mem(): array {
    $m = [];
    foreach (file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [] as $l) {
        if (preg_match('/^(MemTotal|MemAvailable|MemFree):\s+(\d+)/', $l, $mm)) $m[$mm[1]] = (int)$mm[2] * 1024;
    }
    $total = $m['MemTotal'] ?? 0;
    $avail = $m['MemAvailable'] ?? $m['MemFree'] ?? 0;
    return ['total' => $total, 'used' => max(0, $total - $avail)];
}

function sys_net_sample(): array {
    $rx = 0; $tx = 0;
    foreach (file('/proc/net/dev', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $l) {
        $p = str_contains($l, ':') ? explode(':', $l, 2) : null;
        if (!$p) continue;
        $iface = trim($p[0]);
        if ($iface === 'lo' || str_starts_with($iface, 'veth') || str_starts_with($iface, 'docker')) continue;
        $f = preg_split('/\s+/', trim($p[1]));
        $rx += (int)($f[0] ?? 0);
        $tx += (int)($f[8] ?? 0);
    }
    return [$rx, $tx];
}

/** 汇总服务器状态；CPU/网速为间隔采样值 */
function sys_stats(): array {
    [$i1, $t1] = sys_cpu_sample();
    [$rx1, $tx1] = sys_net_sample();
    usleep(150000);
    [$i2, $t2] = sys_cpu_sample();
    [$rx2, $tx2] = sys_net_sample();

    $dTotal = $t2 - $t1;
    $cpu = $dTotal > 0 ? round(100 * (1 - ($i2 - $i1) / $dTotal), 1) : null;
    $interval = 0.15;
    $mem = sys_mem();

    $dataDir = dirname(dirname(__DIR__)) . '/data';
    $diskTotal = @disk_total_space(is_dir($dataDir) ? $dataDir : __DIR__);
    $diskFree = @disk_free_space(is_dir($dataDir) ? $dataDir : __DIR__);

    $load = [0.0, 0.0, 0.0];
    if (is_file('/proc/loadavg')) {
        $load = array_map('floatval', array_slice(preg_split('/\s+/', trim((string)file_get_contents('/proc/loadavg'))), 0, 3));
    }
    $uptime = 0;
    if (is_file('/proc/uptime')) {
        $uptime = (int)floatval(trim((string)file_get_contents('/proc/uptime')));
    }

    return [
        'cpu_pct' => $cpu,
        'mem_used' => $mem['used'],
        'mem_total' => $mem['total'],
        'mem_pct' => $mem['total'] > 0 ? round(100 * $mem['used'] / $mem['total'], 1) : null,
        'disk_total' => $diskTotal ?: 0,
        'disk_free' => $diskFree ?: 0,
        'disk_pct' => ($diskTotal && $diskFree !== false) ? round(100 * (1 - $diskFree / $diskTotal), 1) : null,
        'net_rx_bps' => max(0, (int)(($rx2 - $rx1) / $interval)),
        'net_tx_bps' => max(0, (int)(($tx2 - $tx1) / $interval)),
        'load' => $load,
        'uptime_s' => $uptime,
    ];
}

function human_bytes(float|int $b): string {
    $u = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
    return round($b, $i === 0 ? 0 : 1) . ' ' . $u[$i];
}
