#!/bin/bash
# 老库迁移专项：v1.0.0 旧库 → 新容器自动迁移 → 数据无损 + 新功能可用
set -u
BASE="http://127.0.0.1:18099"
PASS=0; FAIL=0
ok()  { PASS=$((PASS+1)); echo "PASS: $1"; }
bad() { FAIL=$((FAIL+1)); echo "FAIL: $1"; }

curl -s $BASE/health.php | grep -q ok && ok "容器健康" || bad "容器健康"
TZ=$(docker exec itswe-nav-test date +%Z)
[ "$TZ" = "CST" ] && ok "容器时区 Asia/Shanghai ($TZ)" || bad "容器时区 ($TZ)"
V=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query('PRAGMA user_version')->fetchColumn();")
[ "$V" -ge 1 ] && ok "user_version 迁移至 $V" || bad "user_version=$V"
C=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo isset(\$p->query(\"PRAGMA table_info(items)\")->fetchAll()[2]) ? 'y' : 'n';")
COL=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); foreach(\$p->query('PRAGMA table_info(items)') as \$c) if(\$c['name']==='url_lan') echo 'has';")
[ "$COL" = "has" ] && ok "url_lan 列已自动补齐" || bad "url_lan 列缺失"
T=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT COUNT(*) FROM sqlite_master WHERE name='login_throttle'\")->fetchColumn();")
[ "$T" = "1" ] && ok "login_throttle 表已就绪" || bad "login_throttle 缺失"
R=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); \$st=\$p->query('SELECT title,url_lan FROM items'); foreach(\$st as \$r) echo \$r['title'],'|',\$r['url_lan'];")
echo "$R" | grep -q '老卡片' && ok "存量数据无损 ($R)" || bad "存量数据异常 ($R)"
JAR=/tmp/mig-jar.txt; rm -f $JAR
CSRF=$(curl -s -c $JAR $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
CODE=$(curl -s -b $JAR -c $JAR -o /dev/null -w '%{http_code}' -d "csrf=$CSRF&act=login&username=legacy&password=legacy123" $BASE/login.php)
[ "$CODE" = "302" ] && ok "老账号可登录" || bad "老账号登录 $CODE"
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=item.add&group_id=1&title=新卡&url=new.example.com&url_lan=192.168.5.5" $BASE/api.php)
echo "$R" | grep -q '"ok":true' && ok "迁移后新功能可用(item.add)" || bad "item.add 失败 $R"
echo "-----------------------------"
echo "PASS=$PASS FAIL=$FAIL"
exit $FAIL
