<?php
// SPDX-License-Identifier: MIT
// logout.php — 退出登录
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
session_destroy();
header('Location: index.php');   // 退出返回游客首页
