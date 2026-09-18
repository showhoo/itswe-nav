<?php
// e2e 辅助：操作容器内 prefs 表的署名种子
$p = new PDO('sqlite:/app/data/itswe-nav.db');
$a = $argv[1] ?? '';
if ($a === 'delete') { $p->exec("DELETE FROM prefs WHERE k LIKE 'credit_%'"); echo "deleted\n"; }
elseif ($a === 'unlock') { $p->exec('DELETE FROM login_throttle'); echo "unlocked
"; }
elseif ($a === 'empty') {
    $p->exec("INSERT OR IGNORE INTO prefs (user_id,k,v) VALUES (0,'credit_name','')");
    $p->exec("UPDATE prefs SET v='' WHERE k='credit_name'");
    echo "emptied\n";
}
else echo "usage: php credit.php delete|empty\n";
