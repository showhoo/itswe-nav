#!/bin/bash
# cov-test.sh — 全量覆盖测试（约 50 用例：认证/CSRF/权限矩阵/多用户隔离/校验/转义/用户管理/限速/i18n）
# 前置：全新容器（空库）运行本脚本；种子管理员 admin/itswe 自动存在
set -u
BASE="http://127.0.0.1:18099"
SD="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
JAR_A=/tmp/cov-admin.txt
JAR_1=/tmp/cov-u1.txt
JAR_2=/tmp/cov-u2.txt
PAGE=/tmp/cov-page.html
RF=/tmp/cov-r.txt
PASS=0; FAIL=0
ok()  { PASS=$((PASS+1)); echo "PASS: $1"; }
bad() { FAIL=$((FAIL+1)); echo "FAIL: $1"; }
has() { if grep -q "$2" "$3" 2>/dev/null; then ok "$1"; else bad "$1"; fi; }
nohas() { if ! grep -q "$2" "$3" 2>/dev/null; then ok "$1"; else bad "$1"; fi; }
eq()  { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (got: $2)"; fi; }
r() { echo "$1" > $RF; echo "   [resp] $1"; }
csrf_of() { curl -s -c "$1" $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}'; }
login_code() { curl -s -b "$1" -c "$1" -o /dev/null -w '%{http_code}' -d "csrf=$2&act=login&username=$3&password=$4" $BASE/login.php; }

rm -f $JAR_A $JAR_1 $JAR_2
refresh_admin_csrf() {   # 从管理页提取当前会话的有效 CSRF
    CA=$(curl -s -b "$1" $BASE/admin.php | grep -o "const CSRF = '[a-f0-9]*'" | grep -o '[a-f0-9]\{32\}')
}


# ===== A. 会话与 CSRF（6）=====
SC=$(curl -sI $BASE/login.php | grep -i '^set-cookie' | tr -d '\r')
echo "$SC" | grep -qi 'httponly' && ok "A1 Cookie HttpOnly" || bad "A1 Cookie HttpOnly"
echo "$SC" | grep -qi 'samesite=lax' && ok "A2 Cookie SameSite=Lax" || bad "A2 Cookie SameSite=Lax"
CODE=$(curl -s -o /dev/null -w '%{http_code}' -d 'act=group.add&name=x' $BASE/api.php)
eq "A3 未登录无CSRF → 401" "$CODE" "401"
CA=$(csrf_of $JAR_A)

CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api.php")
eq "A6 未登录GET → 401" "$CODE" "401"

# ===== B. 登录（3）=====
eq "B1 种子管理员登录" "$(login_code $JAR_A "$(csrf_of $JAR_A)" admin itswe)" "302"
# 登录会话的有效 token（登录后继续使用同一会话的 CSRF）
CA=$(curl -s -b $JAR_A $BASE/admin.php | grep -o "const CSRF = '[a-f0-9]*'" | grep -o '[a-f0-9]\{32\}')
[ -n "$CA" ] && ok "B1b 已取登录态 token" || bad "B1b 已取登录态 token"
# A4/A5：登录态下缺/错 CSRF → 419
CODE=$(curl -s -b $JAR_A -o /dev/null -w '%{http_code}' -d 'act=group.add&name=x' $BASE/api.php)
eq "A4 登录态缺CSRF → 419" "$CODE" "419"
CODE=$(curl -s -b $JAR_A -o /dev/null -w '%{http_code}' -d "csrf=deadbeef&act=group.add&name=x" $BASE/api.php)
eq "A5 错CSRF → 419" "$CODE" "419"
# 注册默认关闭（安全默认）：先断言拒绝，再由管理员开启
CODE=$(curl -s -b $JAR_1 -c $JAR_1 -o /dev/null -w '%{http_code}' -d "csrf=$(csrf_of $JAR_1)&act=register&username=covclosed&password=cov123456&agree=1" $BASE/register.php)
eq "B1d 注册默认关闭→被拒(200)" "$CODE" "200"
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=reg.toggle&open=1" $BASE/api.php); r "$R"
has "B1e 管理员开启注册" '"ok":true' $RF
C1=$(csrf_of $JAR_1)
eq "B2 注册 u1" "$(curl -s -b $JAR_1 -c $JAR_1 -o /dev/null -w '%{http_code}' -d "csrf=$C1&act=register&username=covu1&password=cov123456&agree=1" $BASE/register.php)" "302"
C2=$(csrf_of $JAR_2)
eq "B3 注册 u2" "$(curl -s -b $JAR_2 -c $JAR_2 -o /dev/null -w '%{http_code}' -d "csrf=$C2&act=register&username=covu2&password=cov123456&agree=1" $BASE/register.php)" "302"

# B4-B9 独立注册页 / 用户协议 / 邮箱唯一
CODE=$(curl -s -b $JAR_1 -c $JAR_1 -o /dev/null -w '%{http_code}' -d "csrf=$C1&act=register&username=covno&password=cov123456" $BASE/register.php)
eq "B4 未勾选协议 → 200 拒绝" "$CODE" "200"
curl -s -b $JAR_1 -d "csrf=$C1&act=register&username=covno&password=cov123456" $BASE/register.php > $PAGE
has "B4b 提示需同意协议" '请先勾选同意用户协议' $PAGE
CODE=$(curl -s -b $JAR_1 -c $JAR_1 -o /dev/null -w '%{http_code}' -d "csrf=$C1&act=register&username=covm1&password=cov123456&agree=1&email=dup@example.com" $BASE/register.php)
eq "B5 带邮箱注册成功" "$CODE" "302"
curl -s -b $JAR_2 -c $JAR_2 -d "csrf=$C2&act=register&username=covm2&password=cov123456&agree=1&email=dup@example.com" $BASE/register.php > $PAGE
has "B6 邮箱重复被拒" '该邮箱已被其他账号使用' $PAGE
CODE=$(curl -s -o /dev/null -w '%{http_code}' $BASE/terms.php)
eq "B7 terms 页 200" "$CODE" "200"
curl -s $BASE/terms.php > $PAGE
has "B8 terms 含协议标题" '用户协议' $PAGE
curl -s $BASE/register.php > $PAGE
has "B9 register 页含协议勾选" 'name="agree"' $PAGE

# ===== C. 权限矩阵：普通用户 vs 管理员专属 act（7）=====
for act in user.add user.del site.set reg.toggle docker.list cache.clear; do
  R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=$act&name=x&id=1&on=1&open=1" $BASE/api.php); r "$R"
  has "C 普通用户 $act 被拒" '需要管理员' $RF
done
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=user.resetpw&id=1&password=xxx123" $BASE/api.php); r "$R"
has "C 普通用户 user.resetpw 被拒" '需要管理员' $RF

# ===== D. 未登录全 act 拒绝（1 条合并断言）=====
ALLOK=1
for act in group.add group.rename group.del group.sort item.add item.update item.del item.sort pref.set icon.upload wallpaper.upload password.change server.stats cache.clear docker.list docker.action user.add user.resetpw user.status user.del site.set reg.toggle; do
  CODE=$(curl -s -o /dev/null -w '%{http_code}' -d "act=$act" $BASE/api.php)
  [ "$CODE" = "401" ] || ALLOK=0
done
[ "$ALLOK" = "1" ] && ok "D 未登录全部 act 401（22 个）" || bad "D 未登录全 act 拒绝"

# ===== E. 多用户隔离（5）=====
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=group.add&name=u1组" $BASE/api.php); r "$R"
has "E u1 建组" '"ok":true' $RF
G1=$(echo "$R" | grep -o '"id":[0-9]*' | grep -o '[0-9]*')
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=item.add&group_id=$G1&title=u1卡&url=u1.example.com" $BASE/api.php); r "$R"
has "E u1 加卡" '"ok":true' $RF
R=$(curl -s -b $JAR_2 -d "csrf=$C2&act=group.rename&id=$G1&name=被改" $BASE/api.php); r "$R"
has "E u2 改 u1 组名被拒" '参数错误' $RF
R=$(curl -s -b $JAR_2 -d "csrf=$C2&act=group.del&id=$G1" $BASE/api.php); r "$R"
has "E u2 删 u1 组被拒" '参数错误' $RF
R=$(curl -s -b $JAR_2 -d "csrf=$C2&act=item.update&id=1&group_id=$G1&title=偷&url=x.example.com" $BASE/api.php); r "$R"
has "E u2 改 u1 卡片被拒" '参数错误' $RF
curl -s -b $JAR_2 $BASE/index.php > $PAGE
nohas "E u2 面板无 u1 组名" 'u1组' $PAGE

# ===== F. 输入校验（6）=====
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=group.rename&id=$G1&name=" $BASE/api.php); r "$R"
has "F 空组名被拒" '参数错误' $RF
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=item.add&group_id=$G1&title=坏URL&url=ht!tp://空格" $BASE/api.php); r "$R"
has "F 非法 URL 被拒" 'URL 无效' $RF
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=pref.set&wallpaper=javascript:alert(1)" $BASE/api.php); r "$R"
has "F javascript: 壁纸被拒" '"ok":false' $RF
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=pref.set&wallpaper=https://ok.example/w(x).png" $BASE/api.php); r "$R"
has "F 括号壁纸被拒" '"ok":false' $RF
R=$(curl -s -b $JAR_2 -d "csrf=$C2&act=site.set&name=33字名称超长超长超长超长超长超长超长超长超长超长超" $BASE/api.php); r "$R"
has "F 普通用户 site.set 被拒" '需要管理员' $RF
LONGNAME=$(python3 -c "print('好'*33)" 2>/dev/null || echo 好好)
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=site.set&name=$LONGNAME" $BASE/api.php); r "$R"
has "F admin 33 字站点名被拒" '"ok":false' $RF

# ===== G. XSS 转义（3）=====
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=item.add&group_id=$G1&title=<script>alert(1)</script>&url=https://x-xss.example.com" $BASE/api.php); r "$R"
has "G XSS 标题可入库" '"ok":true' $RF
curl -s -b $JAR_1 $BASE/index.php > $PAGE
nohas "G 页面无未转义 script" '<script>alert' $PAGE
has "G 标题已转义输出" '&lt;script&gt;' $PAGE

# ===== H. 用户管理全流程（7）=====
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=user.add&username=covu3&password=cov999&role=user" $BASE/api.php); r "$R"
has "H admin 建号" '"ok":true' $RF
CODE=$(login_code $JAR_2 "$(csrf_of $JAR_2)" covu3 cov999)
eq "H covu3 可登录" "$CODE" "302"
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=user.resetpw&username=covu3&password=reset456" $BASE/api.php); r "$R"
has "H 用户名重置密码暂不支持(参数校验)" '"ok":false' $RF
UID3=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT id FROM users WHERE username='covu3'\")->fetchColumn();")
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=user.resetpw&id=$UID3&password=reset456" $BASE/api.php); r "$R"
has "H 按 id 重置密码 ok" '"ok":true' $RF
CODE=$(login_code $JAR_2 "$(csrf_of $JAR_2)" covu3 reset456)
eq "H 重置后新密码可登录" "$CODE" "302"
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=user.status&id=$UID3&on=0" $BASE/api.php); r "$R"
has "H 禁用 ok" '"ok":true' $RF
CODE=$(login_code $JAR_2 "$(csrf_of $JAR_2)" covu3 reset456)
eq "H 禁用后登录被拒" "$CODE" "200"
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=user.status&id=$UID3&on=1" $BASE/api.php); r "$R"
has "H 重新启用 ok" '"ok":true' $RF

# ===== I. 登录限速（4）=====
for i in 1 2 3 4 5 6; do curl -s -b $JAR_A -o /dev/null -d "csrf=$CA&act=login&username=admin&password=bad$i" $BASE/login.php; done
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=login&username=admin&password=itswe" $BASE/login.php); r "$R"
has "I 限流后正确密码也拒" '失败次数过多' $RF
docker cp "$SD/tests/credit.php" itswe-nav-test:/tmp/credit.php 2>/dev/null || docker cp "$SD/credit.php" itswe-nav-test:/tmp/credit.php >/dev/null 2>&1
docker exec itswe-nav-test php /tmp/credit.php unlock >/dev/null
CODE=$(login_code $JAR_A "$CA" admin itswe)
eq "I 解锁后可登录" "$CODE" "302"
refresh_admin_csrf $JAR_A
R=$(curl -s -b $JAR_1 -d "csrf=$C1&act=login&username=covu1&password=cov123456" $BASE/login.php -o /dev/null -w '%{http_code}')
eq "I 限速按用户名隔离(u1 不受影响)" "$R" "302"

# ===== J. i18n 英文管理页（3）=====
echo "  [dbg-J0] sid=$(tail -1 $JAR_A | awk '{print $NF}') CA=${#CA}"
refresh_admin_csrf $JAR_A
echo "  [dbg-J1] 刷新后CA长度=${#CA} admin页状态=$(curl -s -o /dev/null -w '%{http_code}' -b $JAR_A $BASE/admin.php)"
echo "  [dbg] CA=$CA jar=$(tail -1 $JAR_A | awk '{print $NF}')"
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=site.set&lang=en" $BASE/api.php); r "$R"
echo "  [dbg] J响应见上"
has "J 切 en ok" '"ok":true' $RF
curl -s -b $JAR_A $BASE/admin.php > $PAGE
has "J 英文管理页含 Users" '>Users<' $PAGE
has "J html lang=en" 'lang="en"' $PAGE
R=$(curl -s -b $JAR_A -d "csrf=$CA&act=site.set&lang=zh-CN" $BASE/api.php); r "$R"
curl -s -b $JAR_A $BASE/admin.php > $PAGE
has "J 切回中文含用户管理" '用户管理' $PAGE

# ===== 收尾：清理覆盖测试数据（容器即将重建，此步主要为幂等重跑）=====
docker exec itswe-nav-test php /tmp/credit.php unlock >/dev/null 2>&1
echo "-----------------------------"
echo "COVER PASS=$PASS FAIL=$FAIL"
exit $FAIL
