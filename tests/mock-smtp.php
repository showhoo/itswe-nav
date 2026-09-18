<?php
// SPDX-License-Identifier: MIT
// 测试辅助：最小 mock SMTP（e2e 真会话用）。常驻循环 accept（端口探测等空会话直接跳过），
// 收到的客户端命令逐行追加 /tmp/mock-smtp.log；容器销毁即退出，无残留。
$srv = stream_socket_server('tcp://127.0.0.1:2525', $errno, $errstr);
if (!$srv) { fwrite(STDERR, "bind fail: $errstr\n"); exit(1); }
@unlink('/tmp/mock-smtp.log');
for (;;) {
    $conn = @stream_socket_accept($srv, 300);
    if (!$conn) break;
    @fwrite($conn, "220 mock ESMTP\r\n");
    $inData = false;
    $auth = 0;   // AUTH LOGIN 状态：1=等 base64 用户名 2=等 base64 密码
    while (($line = fgets($conn, 1024)) !== false) {
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') { $inData = false; @fwrite($conn, "250 queued\r\n"); }
            continue;   // 信体行不逐行回响应：客户端 DATA 后只读一次最终 250
        }
        file_put_contents('/tmp/mock-smtp.log', $line, FILE_APPEND);
        $cmd = strtoupper(rtrim($line, "\r\n"));
        if ($cmd === '') { @fwrite($conn, "500 bad syntax\r\n"); continue; }
        if (str_starts_with($cmd, 'DATA')) { @fwrite($conn, "354 go\r\n"); $inData = true; }
        elseif (str_starts_with($cmd, 'EHLO')) @fwrite($conn, "250-mock\r\n250 OK\r\n");
        elseif ($cmd === 'AUTH LOGIN') { @fwrite($conn, "334 VXNlcm5hbWU6\r\n"); $auth = 1; }
        elseif ($auth === 1) { @fwrite($conn, "334 UGFzc3dvcmQ6\r\n"); $auth = 2; }   // base64 用户名行→334
        elseif ($auth === 2) { @fwrite($conn, "235 ok\r\n"); $auth = 0; }             // base64 密码行→235
        elseif (str_starts_with($cmd, 'MAIL FROM') || str_starts_with($cmd, 'RCPT TO')) @fwrite($conn, "250 OK\r\n");
        elseif ($cmd === 'QUIT') { @fwrite($conn, "221 bye\r\n"); break; }
        else @fwrite($conn, "250 OK\r\n");
    }
    fclose($conn);
}
fclose($srv);
