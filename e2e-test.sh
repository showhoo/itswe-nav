#!/bin/bash
# itswe-nav e2e 冒烟测试（在容器所在宿主机执行，走 127.0.0.1）
# 账号模型：空库自动播种 admin/itswe；注册用户一律普通用户
set -u
BASE="http://127.0.0.1:18099"
SD="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"   # 脚本自定位（兼容仓库根/run-tests 解包目录两种运行位）
JAR=/tmp/itswe-nav-cookies.txt        # tester1（普通用户）
JAR2=/tmp/itswe-nav-admin.txt         # admin（种子管理员）
PAGE=/tmp/itswe-nav-page.html
RF=/tmp/itswe-nav-r.txt
PASS=0; FAIL=0
ok()  { PASS=$((PASS+1)); echo "PASS: $1"; }
bad() { FAIL=$((FAIL+1)); echo "FAIL: $1"; }
has() { if grep -q "$2" "$3" 2>/dev/null; then ok "$1"; else bad "$1"; fi; }
eq()  { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (got: $2)"; fi; }
nohas() { if ! grep -q "$2" "$3" 2>/dev/null; then ok "$1"; else bad "$1"; fi; }
r() { echo "$1" > $RF; }

docker rm -f itswe-nav-boxtest >/dev/null 2>&1
rm -f $JAR $JAR2

# 1. csrf（游客会话）
CSRF=$(curl -s -c $JAR $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
[ -n "$CSRF" ] && ok "获取 csrf" || bad "获取 csrf"

# 2. 默认管理员 admin/itswe 登录（空库播种；注意 csrf 必须与登录会话同源）
CSRF2=$(curl -s -c $JAR2 $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
CODE=$(curl -s -b $JAR2 -c $JAR2 -o /dev/null -w '%{http_code}' -d "csrf=$CSRF2&act=login&username=admin&password=itswe" $BASE/login.php)
eq "种子管理员登录302" "$CODE" "302"

# 2.1 注册默认关闭（安全默认）：断言拒绝 → 管理员开启
CODE=$(curl -s -b $JAR -c $JAR -o /dev/null -w '%{http_code}' -d "csrf=$CSRF&act=register&username=closedchk&password=closed123&agree=1" $BASE/register.php)
eq "注册默认关闭→被拒(200)" "$CODE" "200"
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=reg.toggle&open=1" $BASE/api.php); r "$R"
has "管理员开启注册" '"ok":true' $RF

# 3. 注册普通用户 tester1（非首个用户 → 普通角色）
CODE=$(curl -s -b $JAR -c $JAR -o /dev/null -w '%{http_code}' -d "csrf=$CSRF&act=register&username=tester1&password=test123456&agree=1" $BASE/register.php)
eq "注册302" "$CODE" "302"

# 3.1 独立注册页与用户协议
curl -s $BASE/register.php > $PAGE
has "register 页含协议勾选" 'name="agree"' $PAGE
has "register 页链接用户协议" 'terms.php' $PAGE
has "register 页含邮箱输入" 'name="email"' $PAGE
curl -s $BASE/terms.php > $PAGE
has "terms 页含用户协议" '用户协议' $PAGE

# 4. admin 面板：编辑模式 + 管理后台入口
curl -s -b $JAR2 $BASE/index.php > $PAGE
has "admin面板含编辑模式" "btn-edit-mode" $PAGE
has "面板头部标题默认思维简约导航" "⯈ 思维简约导航" $PAGE
has "admin面板含管理后台入口" 'href="admin.php"' $PAGE
has "admin面板含系统管理入口（单条，带图标防内嵌字典误命中）" '🔧 系统管理' $PAGE
nohas "admin面板无旧三条直达残留" "tab=server" $PAGE

# 5. 普通用户面板：有编辑模式、无管理后台（角色隔离）
curl -s -b $JAR $BASE/index.php > $PAGE
has "普通用户面板含编辑模式" "btn-edit-mode" $PAGE
nohas "普通用户无管理后台入口" 'href="admin.php"' $PAGE
nohas "普通用户无服务器快捷入口" "tab=server" $PAGE
has "普通用户也有内外网切换按钮" "btn-netmode" $PAGE

# 6. 普通用户：分组 + 卡片（含内网地址）
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=group.add&name=家庭服务" $BASE/api.php); r "$R"
has "group.add ok" '"ok":true' $RF
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=item.add&group_id=1&title=路由器&url=router.example.com&url_lan=http://192.168.0.1&description=管理页" $BASE/api.php); r "$R"
has "item.add ok" '"ok":true' $RF
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=item.add&group_id=1&title=NAS&url=nas.example.com&url_lan=192.168.1.10:5000" $BASE/api.php); r "$R"
has "item.add(无协议内网地址) ok" '"ok":true' $RF
curl -s -b $JAR -d "csrf=$CSRF&act=pref.set&netmode=lan" $BASE/api.php > /dev/null
curl -s -b $JAR $BASE/index.php > $PAGE
has "内网地址无协议自动补 http" 'href="http://192.168.1.10:5000"' $PAGE
curl -s -b $JAR -d "csrf=$CSRF&act=pref.set&netmode=auto" $BASE/api.php > /dev/null

# 7. 图标上传
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > /tmp/t-icon.png
R=$(curl -s -b $JAR -F "csrf=$CSRF" -F "act=icon.upload" -F "file=@/tmp/t-icon.png;type=image/png" $BASE/api.php); r "$R"
has "icon.upload" '"ok":true' $RF
ICONF=$(echo "$R" | grep -o 'i_[a-f0-9]*\.png')
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/media.php?type=icon&f=$ICONF")
eq "media.php 提供图标" "$CODE" "200"
R=$(curl -s -b $JAR -F "csrf=$CSRF" -F "act=icon.upload" -F "file=@/etc/hostname;type=image/png" $BASE/api.php); r "$R"
has "非图片被拒" '"ok":false' $RF

# 8. 壁纸上传并生效
printf 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d > /tmp/t-wall.png
R=$(curl -s -b $JAR -F "csrf=$CSRF" -F "act=wallpaper.upload" -F "file=@/tmp/t-wall.png;type=image/png" $BASE/api.php); r "$R"
has "wallpaper.upload" '"ok":true' $RF
curl -s -b $JAR $BASE/index.php > $PAGE
has "壁纸已应用到页面背景" "media.php?type=wallpaper" $PAGE

# 9. 内外网切换（强制 + XFF 自动判定）
curl -s -b $JAR -d "csrf=$CSRF&act=pref.set&netmode=lan" $BASE/api.php > /dev/null
curl -s -b $JAR $BASE/index.php > $PAGE
has "内网模式卡片指向 192.168.0.1" 'href="http://192.168.0.1"' $PAGE
has "顶部切换按钮存在" "btn-netmode" $PAGE
has "卡片携带双地址数据" 'data-lan="http://192.168.0.1"' $PAGE
has "卡片移动按钮渲染" "data-move-up" $PAGE
has "内网模式头部标签" "🌐 内网" $PAGE
nohas "卡片不再显示内网徽标" "lan-badge" $PAGE
curl -s -b $JAR -d "csrf=$CSRF&act=pref.set&netmode=wan" $BASE/api.php > /dev/null
curl -s -b $JAR $BASE/index.php > $PAGE
has "外网模式卡片指向外网地址" 'href="https://router.example.com"' $PAGE
curl -s -b $JAR -d "csrf=$CSRF&act=pref.set&netmode=auto" $BASE/api.php > /dev/null
curl -s -b $JAR -H "X-Forwarded-For: 8.8.8.8" $BASE/index.php > $PAGE
has "自动模式(公网IP)指向外网" 'href="https://router.example.com"' $PAGE
has "自动模式头部综合标签" "🌐 自动·外网" $PAGE
curl -s -b $JAR -H "X-Forwarded-For: 192.168.3.10" $BASE/index.php > $PAGE
has "自动模式(192.168.x)指向内网" 'href="http://192.168.0.1"' $PAGE

# 10. 服务器状态（登录即可见）
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=server.stats" $BASE/api.php); r "$R"
has "server.stats 有 CPU 数据" "cpu_pct" $RF
has "server.stats 有网速数据" "net_rx_bps" $RF

# 11. Docker 管理（仅管理员）
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=docker.list" $BASE/api.php); r "$R"
has "普通用户 docker.list 被拒" '需要管理员' $RF
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=docker.list" $BASE/api.php); r "$R"
has "admin docker.list 含测试容器" "itswe-nav-test" $RF
docker run -d --name itswe-nav-boxtest busybox sleep 300 >/dev/null 2>&1
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=docker.action&id=itswe-nav-boxtest&do=stop" $BASE/api.php); r "$R"
has "admin docker stop" '"ok":true' $RF
STATE=$(docker inspect -f '{{.State.Status}}' itswe-nav-boxtest)
eq "容器确已停止" "$STATE" "exited"
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=docker.action&id=itswe-nav-boxtest&do=start" $BASE/api.php); r "$R"
has "admin docker start" '"ok":true' $RF
STATE=$(docker inspect -f '{{.State.Status}}' itswe-nav-boxtest)
eq "容器确已恢复" "$STATE" "running"
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=docker.action&id=../etc/passwd&do=start" $BASE/api.php); r "$R"
has "非法容器标识被拒" '"ok":false' $RF

# 12. 站点默认值：游客页站点名「思维简约导航」+ 页脚顺序（署名在前）
curl -s $BASE/ > $PAGE
has "游客页标题含思维简约导航" "思维简约导航" $PAGE
has "默认署名渲染(Powered by)" "Powered by" $PAGE
has "署名图标本地化" 'src="assets/itswe-icon.png"' $PAGE
has "卡片兜底图标本地化" 'ICON_FALLBACK = "assets/itswe-icon.png"' $PAGE
POW=$(grep -bo 'Powered by' $PAGE | head -1 | cut -d: -f1)
SN=$(grep -bo 'class="site-name"' $PAGE | head -1 | cut -d: -f1)
if [ -n "$POW" ] && [ -n "$SN" ] && [ "$POW" -lt "$SN" ]; then ok "页脚顺序：署名在站点名之前"; else bad "页脚顺序：署名在站点名之前 (pow=$POW site=$SN)"; fi

# 13. 退出返回游客首页
LOC=$(curl -sI -b $JAR $BASE/logout.php | grep -i '^location:' | tr -d '\r' | awk '{print $2}')
eq "退出302到游客首页" "$LOC" "index.php"

# 14. 署名三态 + admin 缺失提醒
docker cp "$SD/tests/credit.php" itswe-nav-test:/tmp/credit.php 2>/dev/null || docker cp "$SD/credit.php" itswe-nav-test:/tmp/credit.php
docker exec itswe-nav-test php /tmp/credit.php delete >/dev/null
curl -s $BASE/ > $PAGE
has "删行后署名回退默认仍在" "Powered by" $PAGE
docker exec itswe-nav-test php /tmp/credit.php empty >/dev/null
curl -s $BASE/ > $PAGE
nohas "清空后页脚署名隐藏" "Powered by" $PAGE
curl -s -b $JAR2 $BASE/admin.php > $PAGE
has "admin 显示署名缺失提醒" "credit-warn" $PAGE
docker exec itswe-nav-test php /tmp/credit.php delete >/dev/null
curl -s -b $JAR2 $BASE/admin.php > $PAGE
nohas "恢复后提醒消失" "credit-warn" $PAGE

# 15. 服务器状态条：默认开 → 后台关 → 再开（第 13 节退出测试已销毁 tester1 会话，重新登录）
CSRF=$(curl -s -c $JAR $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
curl -s -b $JAR -c $JAR -o /dev/null -d "csrf=$CSRF&act=login&username=tester1&password=test123456" $BASE/login.php
curl -s -b $JAR $BASE/index.php > $PAGE
has "默认渲染状态条容器" 'id="srv-status"' $PAGE
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&srv_status=0" $BASE/api.php); r "$R"
has "后台关闭状态条 ok" '"ok":true' $RF
curl -s -b $JAR $BASE/index.php > $PAGE
nohas "关闭后面板无状态条" 'id="srv-status"' $PAGE
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&srv_status=1" $BASE/api.php); r "$R"
has "重新开启 ok" '"ok":true' $RF
curl -s -b $JAR $BASE/index.php > $PAGE
has "重开后状态条恢复" 'id="srv-status"' $PAGE

# 16. 未登录隔离
curl -s $BASE/ > $PAGE
nohas "游客页无编辑模式" "btn-edit-mode" $PAGE
nohas "游客页无状态条" 'id="srv-status"' $PAGE
CODE=$(curl -s -o /dev/null -w '%{http_code}' -d 'act=server.stats' $BASE/api.php)
eq "未登录 api 401" "$CODE" "401"

# 17. 健康检查
R=$(curl -s $BASE/health.php)
eq "health ok" "$R" "ok"

# 16. 安全响应头
# 安全头 + 个人设置默认面板标题
curl -s -b $JAR2 $BASE/admin.php > $PAGE
has "个人设置面板标题默认思维简约导航" 'name="title" maxlength="32" value="思维简约导航"' $PAGE
H=$(curl -sI $BASE/health.php)
echo "$H" | grep -qi 'x-frame-options: sameorigin' && ok "安全头 X-Frame-Options" || bad "安全头 X-Frame-Options"
echo "$H" | grep -qi 'x-content-type-options: nosniff' && ok "安全头 nosniff" || bad "安全头 nosniff"

# 17. 服务器状态权限开关（默认关=所有人可见；开启后仅管理员）
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&stats_admin_only=1" $BASE/api.php); r "$R"
has "开启仅管理员 ok" '"ok":true' $RF
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=server.stats" $BASE/api.php); r "$R"
has "普通用户 stats 被拒" '需要管理员' $RF
curl -s -b $JAR $BASE/index.php > $PAGE
if ! grep -q 'id="srv-status"' $PAGE; then ok "普通用户状态条隐藏"; else bad "普通用户状态条隐藏"; fi
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=server.stats" $BASE/api.php); r "$R"
has "管理员 stats 不受限" 'cpu_pct' $RF
curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&stats_admin_only=0" $BASE/api.php > /dev/null
curl -s -b $JAR $BASE/index.php > $PAGE
has "关闭后状态条恢复" 'id="srv-status"' $PAGE

# 17.5 外键级联：删除用户后其卡片连带清除（新库 schema 带 REFERENCES）
JAR4=/tmp/fk.txt; rm -f $JAR4
CSRF4=$(curl -s -c $JAR4 $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
curl -s -b $JAR4 -c $JAR4 -o /dev/null -d "csrf=$CSRF4&act=register&username=botfk&password=bot123456&agree=1" $BASE/register.php
R=$(curl -s -b $JAR4 -d "csrf=$CSRF4&act=group.add&name=fk组" $BASE/api.php); r "$R"
GIDFK=$(echo "$R" | grep -o '"id":[0-9]*' | grep -o '[0-9]*')
curl -s -b $JAR4 -d "csrf=$CSRF4&act=item.add&group_id=$GIDFK&title=fk卡&url=fk.example.com" $BASE/api.php > /dev/null
N0=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query('SELECT COUNT(*) FROM items')->fetchColumn();")
UIDFK=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT id FROM users WHERE username='botfk'\")->fetchColumn();")
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=user.del&id=$UIDFK" $BASE/api.php); r "$R"
has "删除临时用户 ok" '"ok":true' $RF
N1=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query('SELECT COUNT(*) FROM items')->fetchColumn();")
[ "$N1" -lt "$N0" ] && ok "级联清理卡片 ($N0→$N1)" || bad "卡片残留 ($N0→$N1)"

# 17.8 界面语言：默认自动跟随浏览器，可显式覆盖
curl -s -H "Accept-Language: en-US,en;q=0.9" $BASE/ > $PAGE
has "自动识别(en浏览器)" "Start your browsing here" $PAGE
has "en游客面板含Google卡" 'href="https://www.google.com"' $PAGE
has "en游客面板含YouTube卡" 'href="https://www.youtube.com"' $PAGE
nohas "en游客面板无百度卡" 'href="https://www.baidu.com"' $PAGE
has "en游客默认引擎Google" 'value="google" selected' $PAGE
nohas "en游客无百度引擎" 'value="baidu"' $PAGE
curl -s "$BASE/?lang=en" > $PAGE
has "?lang=en 覆盖为英文面板" 'href="https://www.google.com"' $PAGE
has "?lang=en 引擎默认Google" 'value="google" selected' $PAGE
curl -s -H "Accept-Language: zh-CN,zh;q=0.9" $BASE/ > $PAGE
has "自动识别(zh浏览器)" "上网，从这里开始" $PAGE
has "zh游客面板含百度卡" 'href="https://www.baidu.com"' $PAGE
nohas "zh游客面板无Google卡" 'href="https://www.google.com"' $PAGE
has "zh游客默认引擎必应" 'value="bing" selected' $PAGE
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&lang=en" $BASE/api.php); r "$R"
has "显式切 en ok" '"ok":true' $RF
curl -s -H "Accept-Language: zh-CN" $BASE/ > $PAGE
has "显式 en 覆盖 zh 浏览器" "Start your browsing here" $PAGE
has "html lang=en" 'lang="en"' $PAGE
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&lang=auto" $BASE/api.php); r "$R"
has "恢复 auto ok" '"ok":true' $RF
curl -s -H "Accept-Language: zh-CN" $BASE/ > $PAGE
has "auto 恢复后跟随浏览器(zh)" "上网，从这里开始" $PAGE
# 英文站点名：en 界面显示 ITSWE-Nav，zh 界面仍显示中文名
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&lang=en&name=思维简约导航&name_en=ITSWE-Nav" $BASE/api.php); r "$R"
has "设置英文名 ok" '"ok":true' $RF
curl -s $BASE/ > $PAGE
has "en 界面站点名显示 ITSWE-Nav" "ITSWE-Nav" $PAGE
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&lang=zh-CN&name=思维简约导航&name_en=ITSWE-Nav" $BASE/api.php); r "$R"
curl -s $BASE/ > $PAGE
has "zh 界面站点名仍为中文" "思维简约导航" $PAGE
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&lang=auto&name=思维简约导航" $BASE/api.php); r "$R"
has "复位语言与站点名 ok" '"ok":true' $RF

# 17.9 自助改密码
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=password.change&old=wrongold&new=newpass456" $BASE/api.php); r "$R"
has "旧密码错误被拒" '旧密码错误' $RF
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=password.change&old=test123456&new=short" $BASE/api.php); r "$R"
has "新密码过短被拒" '密码至少 6 位' $RF
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=password.change&old=test123456&new=newpass456" $BASE/api.php); r "$R"
has "改密 ok" '"ok":true' $RF
JAR5=/tmp/pw3.txt; rm -f $JAR5
CSRF5=$(curl -s -c $JAR5 $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
curl -s -b $JAR5 -c $JAR5 -o /dev/null -d "csrf=$CSRF5&act=login&username=tester1&password=test123456" $BASE/login.php
curl -s -b $JAR5 $BASE/index.php > $PAGE
if ! grep -q 'btn-add-group' $PAGE; then ok "旧密码已失效(未登录仅见游客页)"; else bad "旧密码应失效"; fi
R=$(curl -s -b $JAR5 -c $JAR5 -d "csrf=$CSRF5&act=login&username=tester1&password=newpass456" $BASE/login.php -o /dev/null -w '%{http_code}')
eq "新密码可登录" "$R" "302"
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=password.change&old=newpass456&new=test123456" $BASE/api.php); r "$R"
has "改回原密码" '"ok":true' $RF

# 17.95 favicon 本地代理缓存
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/favicon.php?h=github.com")
eq "favicon 代理 200" "$CODE" "200"
CT=$(curl -sI "$BASE/favicon.php?h=github.com" | grep -i '^content-type' | tr -d '
')
echo "$CT" | grep -qi 'image/' && ok "favicon 内容类型为图片" || bad "favicon 内容类型 ($CT)"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/favicon.php?h=a")
eq "非法主机名 404" "$CODE" "404"
# favicon.im 对任意主机都可能返回占位图（200），无法抓取时我们的负缓存返回 404
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/favicon.php?h=no-such-host-x8z.invalid")
{ [ "$CODE" = "404" ] || [ "$CODE" = "200" ]; } && ok "不存在域名有兜底响应 ($CODE)" || bad "不存在域名异常 ($CODE)"

# 17.96 备份导出/导入（仅管理员 + 整库往返）
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/backup.php?act=export")
eq "未登录导出 401" "$CODE" "401"
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b $JAR "$BASE/backup.php?act=export")
eq "普通用户导出 403" "$CODE" "403"
docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); \$p->exec(\"UPDATE users SET email='bk@example.com' WHERE username='admin'\");"
curl -s -b $JAR2 "$BASE/backup.php?act=export" > /tmp/bk.json
grep -q '"email"' /tmp/bk.json && ok "备份包含 email" || bad "备份缺 email 字段"
has "导出含应用标识" '"app":"itswe-nav"' /tmp/bk.json
has "导出含用户哈希(可完整恢复)" 'password_hash' /tmp/bk.json
grep -q '"username":"tester1"' /tmp/bk.json && ok "导出含 tester1" || bad "导出含 tester1"
# 导入同一份备份 → 数据往返一致
N0=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query('SELECT COUNT(*) FROM items')->fetchColumn();")
R=$(curl -s -b $JAR2 -F "csrf=$CSRF2" -F "act=import" -F "file=@/tmp/bk.json;type=application/json" $BASE/backup.php); r "$R"
E=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT email FROM users WHERE username='admin'\")->fetchColumn();")
[ "$E" = "bk@example.com" ] && ok "导入后 email 保留" || bad "导入丢失 email: [$E]"
has "导入 ok" '"ok":true' $RF
N1=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query('SELECT COUNT(*) FROM items')->fetchColumn();")
eq "导入后卡片数一致" "$N1" "$N0"
echo 'not json' > /tmp/bk-bad.json
R=$(curl -s -b $JAR2 -F "csrf=$CSRF2" -F "act=import" -F "file=@/tmp/bk-bad.json;type=application/json" $BASE/backup.php); r "$R"
has "坏文件被拒" '备份文件格式不正确' $RF
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b $JAR "$BASE/backup.php?act=export")
eq "普通用户导出备份 403(备份口)" "$CODE" "403"
# —— 旧格式备份（v1.0.0 导出，无 email 键）仍可导入 ——
docker cp /tmp/bk.json itswe-nav-test:/tmp/bk.json
docker exec itswe-nav-test php -r '$j=json_decode(file_get_contents("/tmp/bk.json"),true); foreach($j["users"] as &$r) unset($r["email"]); file_put_contents("/tmp/bk-old.json",json_encode($j,JSON_UNESCAPED_UNICODE));'
docker cp itswe-nav-test:/tmp/bk-old.json /tmp/bk-old.json
R=$(curl -s -b $JAR2 -F "csrf=$CSRF2" -F "act=import" -F "file=@/tmp/bk-old.json;type=application/json" $BASE/backup.php); r "$R"
has "旧格式备份(无email)可导入" '"ok":true' $RF
E=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT email FROM users WHERE username='admin'\")->fetchColumn();")
[ "$E" = "" ] && ok "旧格式导入 email 落空串" || bad "旧格式导入 email 异常: [$E]"
# 语言测试后置：恢复自动（17.8 已把语言设回 auto）……

# 17.98 邮件验证码功能
# 会话此前经历登出/切换，刷新本 jar 的 CSRF（csrf 必须与会话同源）
CSRF=$(curl -s -b $JAR -c $JAR $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=mail.send_code&email=a@b.com&purpose=register" $BASE/api.php); r "$R"
has "未配置 SMTP 发码被拒" '未配置' $RF
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&smtp_host=127.0.0.1&smtp_port=1&smtp_user=t@example.com&smtp_pass=pw&smtp_from=t@example.com" $BASE/api.php); r "$R"
has "SMTP 配置保存 ok" '"ok":true' $RF
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=mail.test&to=t@example.com" $BASE/api.php); r "$R"
has "测试邮件有结构化结果" '"ok"' $RF
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&mail_verify=1" $BASE/api.php); r "$R"
has "开启邮箱验证 ok" '"ok":true' $RF
R=$(curl -s -b $JAR -d "csrf=$CSRF&act=register&username=nocode&password=x123456&email=nocode@example.com&agree=1" $BASE/register.php); r "$R"
has "验证开启后注册缺验证码被拒" '验证码错误' $RF
# 模拟收到验证码：直接播种已知哈希，走完整注册
docker cp "$SD/tests/sim-code.php" itswe-nav-test:/tmp/sim-code.php 2>/dev/null || docker cp "$SD/sim-code.php" itswe-nav-test:/tmp/sim-code.php >/dev/null
docker exec itswe-nav-test php /tmp/sim-code.php sim@example.com 246810 >/dev/null
CODE=$(curl -s -b $JAR -c $JAR -o /dev/null -w '%{http_code}' -d "csrf=$CSRF&act=register&username=simuser&password=sim123456&email=sim@example.com&code=246810&agree=1" $BASE/register.php)
eq "带正确验证码注册成功(302)" "$CODE" "302"
U=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); \$p->exec(\"DELETE FROM users WHERE username='simuser'\"); echo 'ok';")
eq "simuser 已入库" "$U" "ok"
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&mail_verify=0" $BASE/api.php); r "$R"
has "关闭邮箱验证 ok" '"ok":true' $RF
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&smtp_host=&smtp_port=&smtp_user=&smtp_pass=&smtp_from=" $BASE/api.php); r "$R"
has "SMTP 配置清空 ok" '"ok":true' $RF

# 18. 登录限速：无 Cookie 连错 6 次 → 正确密码也被拒（证明持久化而非会话级）
JAR3=/tmp/throttle.txt; rm -f $JAR3
CSRF3=$(curl -s -c $JAR3 $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
for i in 1 2 3 4 5 6; do curl -s -b $JAR3 -o /dev/null -d "csrf=$CSRF3&act=login&username=admin&password=wrong$i" $BASE/login.php; done
R=$(curl -s -b $JAR3 -d "csrf=$CSRF3&act=login&username=admin&password=itswe" $BASE/login.php); r "$R"
has "限流后正确密码也拒" '失败次数过多' $RF
docker cp "$SD/tests/credit.php" itswe-nav-test:/tmp/credit.php 2>/dev/null || docker cp "$SD/credit.php" itswe-nav-test:/tmp/credit.php >/dev/null 2>&1
docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); \$p->exec('DELETE FROM login_throttle'); echo 'unlocked';" > /dev/null
R=$(curl -s -b $JAR3 -d "csrf=$CSRF3&act=login&username=admin&password=itswe" $BASE/login.php -o /dev/null -w '%{http_code}')
eq "清表后可登录" "$R" "302"

# 19.x 图库：gallery: 前缀渲染 + 出口白名单/穿越
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=group.add&name=图库组" $BASE/api.php); r "$R"
has "图库组创建 ok" '"ok":true' $RF
GID=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo (int)\$p->query(\"select id from groups where user_id=(select id from users where username='admin') order by id desc limit 1\")->fetchColumn();")
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=item.add&group_id=$GID&title=gtest&url=https://example.com&icon=gallery:dashboard/demo.webp" $BASE/api.php); r "$R"
has "item.add gallery 图标 ok" '"ok":true' $RF
curl -s -b $JAR2 $BASE/index.php > $PAGE
has "gallery: 前缀转本地出口" 'gallery.php?set=dashboard' $PAGE
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/gallery.php?index=1")
eq "gallery index 空库 200" "$CODE" "200"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/gallery.php?set=dashboard&f=nope.webp")
eq "gallery 缺文件 404" "$CODE" "404"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/gallery.php?set=..%2Fdb&f=x.svg")
eq "gallery 穿越拒绝 404" "$CODE" "404"

# —— R5 补充：找回密码全流程（password.reset）——
# 前置：password.reset 受「SMTP 已配置」门控，先用站点设置配一个假 SMTP（只校验码不走真发信）
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&smtp_host=smtp.example.com&smtp_port=465&smtp_user=reseed@example.com&smtp_pass=dummy123" $BASE/api.php)
r "$R"
has "找回前置：SMTP 已配置" '"ok":true' $RF
JAR_R=/tmp/itswe-nav-reset.txt; rm -f $JAR_R
CSRF_R=$(curl -s -c $JAR_R $BASE/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | grep -o '[a-f0-9]\{32\}')
docker exec itswe-nav-test php /tmp/sim-code.php rsu@example.com 246810 >/dev/null
CODE=$(curl -s -b $JAR -c $JAR -o /dev/null -w '%{http_code}' -d "csrf=$CSRF&act=register&username=resetu&password=old123456&email=rsu@example.com&code=246810&agree=1" $BASE/register.php)
eq "找回前置：resetu 注册成功" "$CODE" "302"
docker exec itswe-nav-test php /tmp/sim-code.php rsu@example.com 135790 reset >/dev/null
R=$(curl -s -b $JAR_R -d "csrf=$CSRF_R&act=password.reset&email=rsu@example.com&code=000000&new=new654321" $BASE/api.php)
r "$R"
has "找回：错验证码被拒" '"ok":false' $RF
R=$(curl -s -b $JAR_R -d "csrf=$CSRF_R&act=password.reset&email=rsu@example.com&code=135790&new=short" $BASE/api.php)
r "$R"
has "找回：新密码过短被拒" '"ok":false' $RF
R=$(curl -s -b $JAR_R -d "csrf=$CSRF_R&act=password.reset&email=rsu@example.com&code=135790&new=new654321" $BASE/api.php)
r "$R"
has "找回：重置成功" '"ok":true' $RF
CODE=$(curl -s -b $JAR_R -c $JAR_R -o /dev/null -w '%{http_code}' -d "csrf=$CSRF_R&act=login&username=resetu&password=new654321" $BASE/login.php)
eq "找回：新密码可登录" "$CODE" "302"
CODE=$(curl -s -b $JAR_R -o /dev/null -w '%{http_code}' -d "csrf=$CSRF_R&act=login&username=resetu&password=old123456" $BASE/login.php)
eq "找回：旧密码已失效(200)" "$CODE" "200"

# —— R5 补充：数据导出（export.php CSV / Netscape HTML）——
CODE=$(curl -s -b $JAR_R -o /dev/null -w '%{http_code}' "$BASE/export.php?format=csv")
eq "CSV 导出 200" "$CODE" "200"
curl -s -b $JAR_R "$BASE/export.php?format=csv" > $PAGE
has "CSV 含表头" '分组' $PAGE
CODE=$(curl -s -b $JAR_R -o /dev/null -w '%{http_code}' "$BASE/export.php?format=html")
eq "HTML 导出 200" "$CODE" "200"
curl -s -b $JAR_R "$BASE/export.php?format=html" | grep -q NETSCAPE && ok "HTML 为 Netscape 格式" || bad "HTML 格式不对"
CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/export.php?format=csv")
eq "未登录导出 401" "$CODE" "401"
CODE=$(curl -s -b $JAR_R -o /dev/null -w '%{http_code}' "$BASE/export.php?format=xml")
eq "未知格式 400" "$CODE" "400"
UID_RU=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo (int)\$p->query(\"SELECT id FROM users WHERE username='resetu'\")->fetchColumn();")
curl -s -b $JAR2 -d "csrf=$CSRF2&act=user.del&id=$UID_RU" $BASE/api.php >/dev/null
ok "清理 resetu(id=$UID_RU)"

# —— R10 回归：跨组移动卡片（item.sort 须迁移 group_id）——
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=group.add&name=移动目标组" $BASE/api.php); r "$R"; has "建移动目标组" '"ok":true' $RF
GID2=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT id FROM \\\"groups\\\" WHERE name='移动目标组'\")->fetchColumn();")
CID=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT id FROM items WHERE user_id=1 ORDER BY id LIMIT 1\")->fetchColumn();")
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=item.sort&group_id=$GID2&ids=$CID" $BASE/api.php); r "$R"; has "跨组 sort 返回 ok" '"ok":true' $RF
NG=$(docker exec itswe-nav-test php -r "\$p=new PDO('sqlite:/app/data/itswe-nav.db'); echo \$p->query(\"SELECT group_id FROM items WHERE id=$CID\")->fetchColumn();")
eq "卡片 group_id 已迁移到目标组" "$NG" "$GID2"
R=$(curl -s -b $JAR2 -d "csrf=$CSRF2&act=site.set&smtp_host=&smtp_user=" $BASE/api.php)
r "$R"
has "找回后：SMTP 已清空" '"ok":true' $RF


echo "-----------------------------"
echo "PASS=$PASS FAIL=$FAIL"
docker rm -f itswe-nav-boxtest >/dev/null 2>&1
exit $FAIL
