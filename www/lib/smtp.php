<?php
// SPDX-License-Identifier: MIT
// lib/smtp.php — 极简 SMTP 客户端（无第三方依赖）
// 支持 465 隐式 SSL 与 587 STARTTLS；AUTH LOGIN；UTF-8 HTML 邮件
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** 读取 SMTP 配置（站点偏好），返回关联数组 */
function smtp_config(): array {
    return [
        'host'  => get_pref(0, 'smtp_host', ''),
        'port'  => (int)(get_pref(0, 'smtp_port', '465') ?: 465),
        'user'  => get_pref(0, 'smtp_user', ''),
        'pass'  => get_pref(0, 'smtp_pass', ''),
        'from'  => get_pref(0, 'smtp_from', '') ?: get_pref(0, 'smtp_user', ''),
        'secure'=> get_pref(0, 'smtp_secure', 'ssl'),   // ssl | tls | none
    ];
}

function smtp_configured(): bool {
    $c = smtp_config();
    return $c['host'] !== '' && $c['user'] !== '';
}

/**
 * 发送 HTML 邮件。成功返回 null，失败返回错误消息（字符串）。
 * 只实现本面板需要的最小命令集：EHLO/AUTH LOGIN/MAIL/RCPT/DATA/QUIT。
 */
function smtp_send(string $to, string $subject, string $html): ?string {
    $c = smtp_config();
    if ($c['host'] === '' || $c['user'] === '') return 'SMTP 未配置';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return '收件人邮箱无效';

    $transport = $c['secure'] === 'ssl' ? 'ssl://' : '';
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client("$transport{$c['host']}:{$c['port']}", $errno, $errstr, 10);
    if (!$fp) return "SMTP 连接失败: $errstr";
    stream_set_timeout($fp, 10);

    $read = function () use ($fp): string {
        $resp = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $resp;
    };
    $talk = function (string $cmd_, string $expect, string $step) use ($fp, $read): ?string {
        fwrite($fp, $cmd_ . "\r\n");
        $resp = $read();
        return str_starts_with($resp, $expect) ? null : "$step 失败: " . trim($resp);
    };

    // 只读横幅不发送任何命令：空命令 CRLF 会被严格服务器（Postfix 等）回 500 并污染 EHLO 读取
    $banner = $read();
    if (!str_starts_with($banner, '220')) { fclose($fp); return '连接失败: ' . trim($banner); }
    if ($err = $talk('EHLO itswe-nav', '250', 'EHLO')) { fclose($fp); return $err; }

    if ($c['secure'] === 'tls') {
        if ($err = $talk('STARTTLS', '220', 'STARTTLS')) { fclose($fp); return $err; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp); return 'STARTTLS 协商失败';
        }
        if ($err = $talk('EHLO itswe-nav', '250', 'EHLO2')) { fclose($fp); return $err; }
    }

    if ($err = $talk('AUTH LOGIN', '334', 'AUTH')) { fclose($fp); return $err; }
    if ($err = $talk(base64_encode($c['user']), '334', 'AUTH用户名')) { fclose($fp); return $err; }
    if ($err = $talk(base64_encode($c['pass']), '235', 'AUTH密码')) { fclose($fp); return $err; }

    if ($err = $talk("MAIL FROM:<{$c['from']}>", '250', 'MAIL FROM')) { fclose($fp); return $err; }
    if ($err = $talk("RCPT TO:<$to>", '250', 'RCPT TO')) { fclose($fp); return $err; }
    if ($err = $talk('DATA', '354', 'DATA')) { fclose($fp); return $err; }

    $eol = "\r\n";
    $headers = 'From: =?UTF-8?B?' . base64_encode('ITSWE-Nav') . "?= <{$c['from']}>" . $eol
             . "To: <$to>" . $eol
             . 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=' . $eol
             . 'Date: ' . date('r') . $eol
             . 'MIME-Version: 1.0' . $eol
             . 'Content-Type: text/html; charset=UTF-8' . $eol
             . 'Content-Transfer-Encoding: base64' . $eol . $eol;
    $body = chunk_split(base64_encode($html));
    fwrite($fp, $headers . $body . $eol . '.' . $eol);
    $resp = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $resp .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    $bad = !str_starts_with($resp, '250') ? '发送被拒: ' . trim($resp) : null;
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $bad;
}
