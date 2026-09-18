<?php
// 测试辅助：为指定邮箱播种已知验证码（e2e 用）；argv: <email> [code] [purpose]
$p = new PDO('sqlite:/app/data/itswe-nav.db');
$email = $argv[1] ?? 'sim@example.com';
$code = $argv[2] ?? '246810';
$purpose = $argv[3] ?? 'register';
$p->prepare('INSERT OR REPLACE INTO mail_codes (email,purpose,code_hash,expires,attempts,last_sent) VALUES (?,?,?,?,0,?)')
  ->execute([$email, $purpose, hash('sha256', $code . '|' . $email . '|' . $purpose), time() + 600, time()]);
echo "seeded $email / $purpose / $code\n";
