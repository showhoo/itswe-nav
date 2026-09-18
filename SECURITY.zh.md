# 安全政策

## 报告漏洞

请**不要**在公开 Issue 中披露安全漏洞。

使用 GitHub 的「私密漏洞报告」（Private vulnerability reporting）功能提交：
仓库页 → **Security** 标签 → **Report a vulnerability**。我们会尽快回复并跟进修复。

## 支持范围

仅最新发布版本的默认部署形态（单容器、SQLite）。

## 安全设计速览

- 密码 bcrypt 哈希存储；会话 Cookie `HttpOnly + SameSite=Lax`，HTTPS 下自动附加 `Secure`
- 全部写操作走 CSRF 校验；登录失败限速（用户名 + IP，10 分钟窗口）
- 上传文件 MIME 实测 + 随机文件名，存于 docroot 之外、经白名单出口读取
- 图库文件出口对 SVG 附加 `default-src 'none'` CSP
- 面板本体无任何统计、上报或远程校验组件
