<?php
// SPDX-License-Identifier: MIT
// lib/db.php — PDO（SQLite）连接、自动建库与增量迁移
declare(strict_types=1);

// 数据库路径：可用环境变量 DB_PATH 覆盖（compose 变体无需改代码）
function db_path(): string {
    return getenv('DB_PATH') ?: (__DIR__ . '/../../data/itswe-nav.db');
}

const DB_VERSION = 5;   // 当前结构版本；结构变更须新增迁移步骤并递增

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $path = db_path();
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $fresh = !is_file($path);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');   // 新库 schema 带 REFERENCES；老库无声明则此开关无副作用
        if ($fresh) init_schema($pdo);
        migrate($pdo);
        seed_defaults($pdo);
    }
    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'user' CHECK (role IN ('admin','user')),
    status        INTEGER NOT NULL DEFAULT 1,
    email         TEXT    NOT NULL DEFAULT '',
    created_at    TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS mail_codes (
    email      TEXT    NOT NULL,
    purpose    TEXT    NOT NULL,
    code_hash  TEXT    NOT NULL,
    expires    INTEGER NOT NULL,
    attempts   INTEGER NOT NULL DEFAULT 0,
    last_sent  INTEGER NOT NULL DEFAULT 0,
    hour_start INTEGER NOT NULL DEFAULT 0,
    hour_count INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (email, purpose)
);
CREATE TABLE IF NOT EXISTS "groups" (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name    TEXT    NOT NULL,
    sort    INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_groups_user ON "groups"(user_id);
CREATE TABLE IF NOT EXISTS items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    group_id    INTEGER NOT NULL REFERENCES "groups"(id) ON DELETE CASCADE,
    title       TEXT    NOT NULL,
    url         TEXT    NOT NULL,
    url_lan     TEXT    NOT NULL DEFAULT '',
    icon        TEXT    NOT NULL DEFAULT '',
    description TEXT    NOT NULL DEFAULT '',
    sort        INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_items_user  ON items(user_id);
CREATE INDEX IF NOT EXISTS idx_items_group ON items(group_id);
CREATE TABLE IF NOT EXISTS prefs (
    user_id INTEGER NOT NULL,
    k       TEXT    NOT NULL,
    v       TEXT    NOT NULL DEFAULT '',
    PRIMARY KEY (user_id, k)
);
CREATE TABLE IF NOT EXISTS login_throttle (
    ip_hash   TEXT    NOT NULL,
    username  TEXT    NOT NULL,
    fails     INTEGER NOT NULL DEFAULT 0,
    last_fail INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (ip_hash, username)
);
SQL);
}

/**
 * 增量迁移：跨版本升级老库时自动补结构。
 * 规则：只增（新列/新表/新索引/补种子），不重命名、不删除、不改存量数据，
 * 因此升级后回滚到旧版本代码也能继续工作。
 */
function migrate(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    $migrations = [
        1 => function (PDO $p) {   // v1.0.0 老库缺 items.url_lan
            foreach ($p->query('PRAGMA table_info(items)') as $c) {
                if ($c['name'] === 'url_lan') return;
            }
            $p->exec("ALTER TABLE items ADD COLUMN url_lan TEXT NOT NULL DEFAULT ''");
        },
        2 => function (PDO $p) {   // 登录限速表（老库建库时无此表）
            $p->exec('CREATE TABLE IF NOT EXISTS login_throttle (
                ip_hash   TEXT    NOT NULL,
                username  TEXT    NOT NULL,
                fails     INTEGER NOT NULL DEFAULT 0,
                last_fail INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (ip_hash, username)
            )');
        },
        3 => function (PDO $p) {   // 邮箱字段 + 邮件验证码表
            $has = false;
            foreach ($p->query('PRAGMA table_info(users)') as $c) {
                if ($c['name'] === 'email') { $has = true; break; }
            }
            if (!$has) $p->exec("ALTER TABLE users ADD COLUMN email TEXT NOT NULL DEFAULT ''");
            $p->exec('CREATE TABLE IF NOT EXISTS mail_codes (
                email     TEXT    NOT NULL,
                purpose   TEXT    NOT NULL,
                code_hash TEXT    NOT NULL,
                expires   INTEGER NOT NULL,
                attempts  INTEGER NOT NULL DEFAULT 0,
                last_sent INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (email, purpose)
            )');
        },
        4 => function (PDO $p) {   // 验证码每小时配额列
            foreach (['hour_start', 'hour_count'] as $col) {
                $has = false;
                foreach ($p->query('PRAGMA table_info(mail_codes)') as $c) {
                    if ($c['name'] === $col) { $has = true; break; }
                }
                if (!$has) $p->exec("ALTER TABLE mail_codes ADD COLUMN $col INTEGER NOT NULL DEFAULT 0");
            }
        },
        5 => function (PDO $p) {   // users.email 部分唯一索引（空邮箱不参与）+ 每 IP 发码限速表
            $p->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_unique ON users(email) WHERE email <> ''");
            $p->exec('CREATE TABLE IF NOT EXISTS mail_throttle (
                ip_hash      TEXT    NOT NULL,
                purpose      TEXT    NOT NULL,
                cnt          INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (ip_hash, purpose)
            )');
        },
    ];
    foreach ($migrations as $to => $fn) {
        if ($v < $to) $fn($pdo);
    }
    if ($v < DB_VERSION) $pdo->exec('PRAGMA user_version = ' . DB_VERSION);
}

/** 默认值播种：幂等（行被删则恢复默认）；管理员仅空库时播种 */
function seed_defaults(PDO $pdo): void {
    $pdo->exec("INSERT OR IGNORE INTO prefs (user_id, k, v) VALUES (0, 'site_name', '思维简约导航')");
    $pdo->exec("INSERT OR IGNORE INTO prefs (user_id, k, v) VALUES (0, 'srv_status', '1')");
    $pdo->exec("INSERT OR IGNORE INTO prefs (user_id, k, v) VALUES (0, 'credit_name', 'ITSWE-Nav 简约导航')");
    $pdo->exec("INSERT OR IGNORE INTO prefs (user_id, k, v) VALUES (0, 'credit_url', 'https://www.itswe.com')");
    $pdo->exec("INSERT OR IGNORE INTO prefs (user_id, k, v) VALUES (0, 'credit_icon', 'assets/itswe-icon.png')");
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO users (username, password_hash, role) VALUES ('admin', '" . password_hash('itswe', PASSWORD_DEFAULT) . "', 'admin')");
    }
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
