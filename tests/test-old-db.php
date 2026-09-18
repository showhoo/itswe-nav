<?php
// 构造 v1.0.0 时代的旧库（无 url_lan 列、无 login_throttle 表、user_version=0）
$p = new PDO('sqlite:/app/data/itswe-nav.db');
$p->exec('PRAGMA journal_mode = WAL');
$p->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'user', status INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')))");
$p->exec('CREATE TABLE "groups" (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, name TEXT NOT NULL, sort INTEGER NOT NULL DEFAULT 0)');
$p->exec("CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, group_id INTEGER NOT NULL, title TEXT NOT NULL, url TEXT NOT NULL, icon TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '', sort INTEGER NOT NULL DEFAULT 0)");
$p->exec('CREATE TABLE prefs (user_id INTEGER NOT NULL, k TEXT NOT NULL, v TEXT NOT NULL DEFAULT \'\', PRIMARY KEY (user_id, k))');
$p->exec("INSERT INTO users (username, password_hash, role) VALUES ('legacy', '" . password_hash('legacy123', PASSWORD_DEFAULT) . "', 'admin')");
$p->exec('INSERT INTO "groups" (user_id, name, sort) VALUES (1, \'老分组\', 1)');
$p->exec("INSERT INTO items (user_id, group_id, title, url, icon, description, sort) VALUES (1, 1, '老卡片', 'https://old.example.com', '', '迁移前就存在', 1)");
echo "old db created, user_version=" . $p->query('PRAGMA user_version')->fetchColumn() . PHP_EOL;
