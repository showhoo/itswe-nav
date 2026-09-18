<?php
// SPDX-License-Identifier: MIT
// lib/docker.php — Docker Engine API 最小客户端（unix socket，无第三方依赖）
declare(strict_types=1);

function docker_sock(): string {
    return getenv('DOCKER_SOCK') ?: '/var/run/docker.sock';
}

function docker_available(): bool {
    return is_file(docker_sock()) || file_exists(docker_sock());
}

/**
 * 调用 Docker Engine API。
 * 返回 ['http' => int, 'body' => array|string]；连接失败抛 RuntimeException。
 * $timeoutMs：动作类调用（stop 等待优雅退出最长 10s）需放宽。
 */
function docker_api(string $method, string $path, int $timeoutMs = 5000): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_UNIX_SOCKET_PATH => docker_sock(),
        // 不带版本前缀 = 守护进程按自身版本解析，兼容老 Docker（20.10 = API 1.41 等）
        CURLOPT_URL => 'http://localhost' . $path,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => 2000,
        CURLOPT_TIMEOUT_MS => $timeoutMs,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Content-Length: 0'],
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new RuntimeException('Docker socket 连接失败: ' . $err);
    $json = json_decode((string)$body, true);
    return ['http' => $http, 'body' => is_array($json) ? $json : (string)$body];
}

/** 容器列表（含已停止），按状态与名称排序 */
function docker_list(): array {
    $r = docker_api('GET', '/containers/json?all=1&limit=500');
    if ($r['http'] !== 200 || !is_array($r['body'])) throw new RuntimeException('Docker API 响应异常');
    $out = [];
    foreach ($r['body'] as $c) {
        $name = isset($c['Names'][0]) ? ltrim($c['Names'][0], '/') : substr($c['Id'], 0, 12);
        $ports = [];
        foreach (($c['Ports'] ?? []) as $p) {
            if (($p['PublicPort'] ?? 0) && ($p['PrivatePort'] ?? 0)) $ports[] = $p['PublicPort'] . '→' . $p['PrivatePort'];
            elseif ($p['PrivatePort'] ?? 0) $ports[] = (string)$p['PrivatePort'];
        }
        $out[] = [
            'id' => substr($c['Id'], 0, 12),
            'name' => $name,
            'image' => $c['Image'] ?? '',
            'state' => $c['State'] ?? 'unknown',      // running / exited / paused ...
            'status' => $c['Status'] ?? '',            // Up 3 days / Exited (0) 2 hours ago
            'ports' => implode(', ', array_slice($ports, 0, 4)),
        ];
    }
    usort($out, fn($a, $b) => [$a['state'], $a['name']] <=> [$b['state'], $b['name']]);
    return $out;
}

/** 启动/停止/重启容器；动作仅限白名单 */
function docker_action(string $id, string $action): void {
    if (!in_array($action, ['start', 'stop', 'restart'], true)) {
        throw new RuntimeException('不支持的动作');
    }
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $id)) {
        throw new RuntimeException('容器标识不合法');
    }
    $r = docker_api('POST', '/containers/' . rawurlencode($id) . '/' . $action, 35000);
    // 304 = 已处于目标状态（如重复启动），视为成功
    if (!in_array($r['http'], [200, 204, 304], true)) {
        throw new RuntimeException('Docker 返回 HTTP ' . $r['http']);
    }
}
