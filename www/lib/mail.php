<?php
// SPDX-License-Identifier: MIT
// lib/mail.php — 邮箱验证码：发送 / 校验
// 规则：6 位数字、10 分钟有效、同一邮箱 60 秒冷却、同一邮箱每小时最多 5 封、
//       校验最多 5 次尝试（防爆破）；验证码只存哈希。
declare(strict_types=1);

require_once __DIR__ . '/smtp.php';
require_once __DIR__ . '/i18n.php';

const MAIL_CODE_TTL = 600;        // 验证码有效期 10 分钟
const MAIL_COOLDOWN = 60;         // 同邮箱发送冷却 60 秒
const MAIL_MAX_ATTEMPTS = 5;      // 校验失败次数上限
const MAIL_HOURLY_LIMIT = 5;      // 同邮箱每小时最多发送封数
const MAIL_PURPOSES = ['register', 'reset'];

function mail_configured(): bool {
    return smtp_configured();
}

/** 发送验证码。返回 null=成功，否则错误消息（已 i18n） */
function mail_send_code(string $email, string $purpose, bool $isIpTrusted = false): ?string {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return t('err_email_invalid');
    if (!in_array($purpose, MAIL_PURPOSES, true)) return t('err_param');

    $pdo = db();
    $now = time();
    $pdo->exec('DELETE FROM mail_codes WHERE expires < ' . ($now - 86400));

    $st = $pdo->prepare('SELECT last_sent, expires, hour_start, hour_count FROM mail_codes WHERE email = ? AND purpose = ?');
    $st->execute([$email, $purpose]);
    $row = $st->fetch();
    if ($row) {
        if ($now - (int)$row['last_sent'] < MAIL_COOLDOWN) return t('err_mail_cooldown');
        $hourStart = (int)($row['hour_start'] ?? 0);
        $hourCount = (int)($row['hour_count'] ?? 0);
        if ($now - $hourStart < 3600 && $hourCount >= MAIL_HOURLY_LIMIT) return t('err_mail_limit');
    }
    // 每小时配额：窗口过期则重置；同邮箱每小时最多 MAIL_HOURLY_LIMIT 封
    $hourStart = $now;
    $hourCount = 1;
    if ($row) {
        if ($now - (int)$row['last_sent'] < MAIL_COOLDOWN) return t('err_mail_cooldown');
        if ($now - (int)($row['hour_start'] ?? 0) < 3600) {
            $hourStart = (int)($row['hour_start'] ?? $now);
            $hourCount = (int)($row['hour_count'] ?? 0) + 1;
            if ($hourCount > MAIL_HOURLY_LIMIT) return t('err_mail_limit');
        }
    }
    $code = (string)random_int(100000, 999999);
    $codeHash = hash('sha256', $code . '|' . $email . '|' . $purpose);

    $pdo->prepare('INSERT INTO mail_codes (email, purpose, code_hash, expires, attempts, last_sent, hour_start, hour_count)
                   VALUES (?,?,?,?,0,?,?,?)
                   ON CONFLICT(email, purpose) DO UPDATE SET
                     code_hash = excluded.code_hash,
                     expires   = excluded.expires,
                     attempts  = 0,
                     last_sent = excluded.last_sent,
                     hour_start = excluded.hour_start,
                     hour_count = excluded.hour_count')
        ->execute([$email, $purpose, $codeHash, $now + MAIL_CODE_TTL, $now, $hourStart, $hourCount]);

    $html = '<div style="font-family:sans-serif;max-width:480px;margin:0 auto">'
          . '<h2 style="color:#1e232c">' . e(get_pref(0, 'site_name', '思维简约导航')) . '</h2>'
          . '<p style="color:#4e5969">' . e(t('mail_code_intro')) . '</p>'
          . '<p style="font-size:28px;font-weight:700;letter-spacing:6px;color:#4d6bfe">' . $code . '</p>'
          . '<p style="color:#86909c;font-size:12px">' . e(t('mail_code_footer')) . '</p></div>';

    return smtp_send($email, t($purpose === 'reset' ? 'mail_subject_reset' : 'mail_subject'), $html);
}

/** 校验验证码。成功时清除该码；返回 null=通过，否则错误消息 */
function mail_check_code(string $email, string $purpose, string $code): ?string {
    $email = strtolower(trim($email));
    $pdo = db();
    $now = time();
    $st = $pdo->prepare('SELECT code_hash, expires, attempts FROM mail_codes WHERE email = ? AND purpose = ?');
    $st->execute([$email, $purpose]);
    $row = $st->fetch();
    if (!$row) return t('err_code_wrong');
    if ((int)$row['expires'] < $now || (int)$row['attempts'] >= MAIL_MAX_ATTEMPTS) {
        $pdo->prepare('DELETE FROM mail_codes WHERE email = ? AND purpose = ?')->execute([$email, $purpose]);
        return t('err_code_expired');
    }
    if (!hash_equals((string)$row['code_hash'], hash('sha256', trim($code) . '|' . $email . '|' . $purpose))) {
        $pdo->prepare('UPDATE mail_codes SET attempts = attempts + 1 WHERE email = ? AND purpose = ?')->execute([$email, $purpose]);
        return t('err_code_wrong');
    }
    $pdo->prepare('DELETE FROM mail_codes WHERE email = ? AND purpose = ?')->execute([$email, $purpose]);
    return null;
}
