<?php
/**
 * 邮件发送模块
 * 使用 PHP 原生 mail() 或 SMTP socket 发送
 * 不依赖 Composer / PHPMailer，纯原生实现
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ====== 获取SMTP配置 ======
function getMailConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $pdo = getDB();
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'mail_%'");
    $cfg = [];
    foreach ($stmt->fetchAll() as $row) {
        $cfg[$row['setting_key']] = $row['setting_value'];
    }
    return $cfg;
}

// ====== 渲染邮件模板 ======
function renderMailTemplate(string $tplKey, array $vars): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT subject, body FROM mail_templates WHERE tpl_key = ?');
    $stmt->execute([$tplKey]);
    $tpl = $stmt->fetch();
    if (!$tpl) {
        // 降级模板：根据 tpl_key 返回合适的默认内容
        $fallbacks = [
            'bot_approved' => [
                'subject' => '[{site_name}] 你的Bot「{bot_name}」已通过审核',
                'body'    => '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:12px;">
  <h2 style="color:#34c759;">✅ 审核已通过</h2>
  <p>你好，<strong>{username}</strong>！</p>
  <p>你的 Bot「<strong>{bot_name}</strong>」已通过审核，现在对外公开展示。</p>
  <p><a href="{bot_url}" style="display:inline-block;padding:10px 24px;background:#0a84ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">查看详情</a></p>
  <hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">
  <p style="font-size:12px;color:#999;">此邮件由 {site_name} 系统自动发送，请勿回复。</p>
</div>',
            ],
            'bot_rejected' => [
                'subject' => '[{site_name}] 你的Bot「{bot_name}」审核未通过',
                'body'    => '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:12px;">
  <h2 style="color:#ff3b30;">❌ 审核未通过</h2>
  <p>你好，<strong>{username}</strong>！</p>
  <p>你的 Bot「<strong>{bot_name}</strong>」审核未通过。</p>
  <p><strong>拒绝原因：</strong>{reject_reason}</p>
  <p>请根据原因修改后重新提交。</p>
  <p><a href="{edit_url}" style="display:inline-block;padding:10px 24px;background:#0a84ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">修改并重新提交</a></p>
  <hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">
  <p style="font-size:12px;color:#999;">此邮件由 {site_name} 系统自动发送，请勿回复。</p>
</div>',
            ],
            'bot_revoked' => [
                'subject' => '[{site_name}] 你的Bot「{bot_name}」已被撤回',
                'body'    => '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;padding:24px;background:#f9f9f9;border-radius:12px;">
  <h2 style="color:#ff9500;">↩️ 审核已撤回</h2>
  <p>你好，<strong>{username}</strong>！</p>
  <p>你的 Bot「<strong>{bot_name}</strong>」已被管理员从已通过列表中撤回，暂时不再公开展示。</p>
  <p><strong>撤回原因：</strong>{revoke_reason}</p>
  <p>请根据原因修改后重新提交审核。</p>
  <p><a href="{edit_url}" style="display:inline-block;padding:10px 24px;background:#0a84ff;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">修改并重新提交</a></p>
  <hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">
  <p style="font-size:12px;color:#999;">此邮件由 {site_name} 系统自动发送，请勿回复。</p>
</div>',
            ],
        ];
        if (isset($fallbacks[$tplKey])) {
            $tpl = $fallbacks[$tplKey];
        } else {
            return [
                'subject' => '[' . ($vars['site_name'] ?? '') . '] 验证码',
                'body'    => '你的验证码是：' . ($vars['code'] ?? '') . '，10分钟内有效。'
            ];
        }
    }
    $search  = array_map(fn($k) => '{' . $k . '}', array_keys($vars));
    $replace = array_values($vars);
    return [
        'subject' => str_replace($search, $replace, $tpl['subject']),
        'body'    => str_replace($search, $replace, $tpl['body']),
    ];
}

// ====== 核心SMTP发送（原生socket，支持SSL/TLS） ======
function smtpSend(string $to, string $subject, string $htmlBody): bool {
    $cfg = getMailConfig();
    $host       = $cfg['mail_host']       ?? '';
    $port       = (int)($cfg['mail_port'] ?? 465);
    $user       = $cfg['mail_username']   ?? '';
    $pass       = $cfg['mail_password']   ?? '';
    $fromName   = $cfg['mail_from_name']  ?? 'TRPG Bot 导航';
    $encryption = strtolower($cfg['mail_encryption'] ?? 'ssl');

    if (!$host || !$user || !$pass) {
        error_log('[Mailer] SMTP未配置');
        return false;
    }

    // 构造 MIME 邮件
    $boundary = md5(uniqid());
    $subject64 = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $from64    = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$from64} <{$user}>\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: {$subject64}\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "Message-ID: <" . md5(uniqid()) . "@" . ($cfg['mail_host'] ?? 'localhost') . ">\r\n";

    $rawMail = $headers . "\r\n" . $htmlBody;

    // 连接
    $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
    if (!$socket) {
        error_log("[Mailer] 连接失败: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, 10);

    $read = function() use ($socket) {
        $resp = '';
        while ($line = fgets($socket, 512)) {
            $resp .= $line;
            if ($line[3] === ' ') break;
        }
        return $resp;
    };
    $send = function(string $cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // banner
    $send('EHLO ' . gethostname());

    // STARTTLS
    if ($encryption === 'tls') {
        $send('STARTTLS');
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send('EHLO ' . gethostname());
    }

    // 认证
    $send('AUTH LOGIN');
    $send(base64_encode($user));
    $r = $send(base64_encode($pass));
    if (strpos($r, '235') === false) {
        error_log("[Mailer] 认证失败: $r");
        fclose($socket);
        return false;
    }

    $send("MAIL FROM: <{$user}>");
    $send("RCPT TO: <{$to}>");
    $send('DATA');
    fwrite($socket, $rawMail . "\r\n.\r\n");
    $r = $read();
    $send('QUIT');
    fclose($socket);

    $ok = strpos($r, '250') !== false;
    if (!$ok) error_log("[Mailer] 发送失败: $r");
    return $ok;
}

// ====== 生成并存储验证码 ======
function generateVerifyCode(string $email, string $purpose): string {
    $pdo  = getDB();
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    // 先作废旧的未使用验证码
    $pdo->prepare('UPDATE verify_codes SET used=1 WHERE email=? AND purpose=? AND used=0')
        ->execute([$email, $purpose]);
    // 插入新验证码，10分钟有效
    $pdo->prepare('INSERT INTO verify_codes (email, code, purpose, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))')
        ->execute([$email, $code, $purpose]);
    return $code;
}

// ====== 校验验证码 ======
function verifyCode(string $email, string $purpose, string $inputCode): bool {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id FROM verify_codes WHERE email=? AND purpose=? AND code=? AND used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email, $purpose, $inputCode]);
    $row = $stmt->fetch();
    if (!$row) return false;
    // 标记已使用
    $pdo->prepare('UPDATE verify_codes SET used=1 WHERE id=?')->execute([$row['id']]);
    return true;
}

// ====== 发送验证码邮件 ======
function sendVerifyCode(string $email, string $purpose, string $username = ''): bool {
    $pdo      = getDB();
    $siteName = '';
    $stmt     = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key='site_name'");
    $stmt->execute();
    $siteName = $stmt->fetchColumn() ?: 'TRPG Bot 导航';

    $code = generateVerifyCode($email, $purpose);
    $tpl  = renderMailTemplate($purpose, [
        'code'      => $code,
        'site_name' => $siteName,
        'username'  => $username ?: $email,
    ]);
    return smtpSend($email, $tpl['subject'], $tpl['body']);
}
