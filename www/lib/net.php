<?php
// SPDX-License-Identifier: MIT
// lib/net.php — 内外网切换：CIDR 匹配与卡片地址选择
declare(strict_types=1);

/** 客户端 IP 是否落在 CIDR 网段（支持 v4 与 v6 前缀） */
function ip_in_cidr(string $ip, string $cidr): bool {
    if (!str_contains($cidr, '/')) return strcasecmp($ip, $cidr) === 0;
    [$net, $bits] = explode('/', $cidr, 2);
    $bits = (int)$bits;
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton(trim($net));
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
    $maxBits = strlen($ipBin) * 8;
    if ($bits < 0 || $bits > $maxBits) return false;
    $fullBytes = intdiv($bits, 8);
    if ($fullBytes && substr($ipBin, 0, $fullBytes) !== substr($netBin, 0, $fullBytes)) return false;
    $remBits = $bits % 8;
    if ($remBits === 0) return true;
    $mask = 0xFF << (8 - $remBits) & 0xFF;
    return (ord($ipBin[$fullBytes]) & $mask) === (ord($netBin[$fullBytes]) & $mask);
}

/** 客户端是否属于配置的内网网段列表（逗号分隔） */
function client_is_lan(string $clientIp, string $cidrList): bool {
    foreach (explode(',', $cidrList) as $c) {
        $c = trim($c);
        if ($c !== '' && ip_in_cidr($clientIp, $c)) return true;
    }
    return false;
}

/**
 * 计算卡片的生效地址。
 * $netmode: auto | lan | wan；auto 按客户端 IP 与站点配置网段判定。
 * 内网地址为空的卡片一律用外网地址。
 */
function item_effect_url(array $it, string $netmode, bool $clientLan): string {
    $lan = trim($it['url_lan'] ?? '');
    if ($lan === '') return $it['url'];
    if ($netmode === 'lan') return $lan;
    if ($netmode === 'wan') return $it['url'];
    return $clientLan ? $lan : $it['url'];
}

/** 直连对端是否为内网/回环地址（即面板置于反代之后） */
function peer_is_trusted(string $remote): bool {
    return $remote !== '' && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * 取客户端真实 IP。仅当直连对端为内网/回环（反代后）才采信 X-Forwarded-For，
 * 且从右往左取第一个公网地址——防止伪造首段绕过登录限速或伪装内网来源；
 * 直连部署（对端为公网地址）一律以 REMOTE_ADDR 为准。
 */
function client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote !== '' && peer_is_trusted($remote)) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff !== '') {
            $parts = array_reverse(array_map('trim', explode(',', $xff)));
            foreach ($parts as $cand) {
                if (filter_var($cand, FILTER_VALIDATE_IP)
                    && filter_var($cand, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $cand;
                }
            }
            $first = $parts[count($parts) - 1] ?? '';
            if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
        }
    }
    return $remote;
}

/** 内网网段列表（站点设置可覆盖；未配置时用常见私网段默认值） */
function lan_cidr_list(): string {
    $v = get_pref(0, 'lan_cidrs', '');
    return $v !== '' ? $v : '192.168.0.0/16,10.0.0.0/8,172.16.0.0/12';
}
