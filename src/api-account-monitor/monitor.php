<?php
/**
 * 余额监控告警脚本
 * CLI 运行：php monitor.php
 * 检测所有账号余额，低于阈值时发送邮件告警
 */

$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    echo "[" . date('Y-m-d H:i:s') . "] 配置文件不存在\n";
    exit(1);
}

$config = json_decode(file_get_contents($configFile), true);
if (!$config) {
    echo "[" . date('Y-m-d H:i:s') . "] 配置文件解析失败\n";
    exit(1);
}

$alert = $config['alert'] ?? null;
if (!$alert || empty($alert['threshold'])) {
    echo "[" . date('Y-m-d H:i:s') . "] 未配置告警阈值，跳过\n";
    exit(0);
}

$threshold = floatval($alert['threshold']);
$alerts = [];

foreach ($config['sites'] as $site) {
    $rate = floatval($site['rate'] ?? 1);
    $siteName = $site['name'];

    foreach ($site['accounts'] as $account) {
        $result = queryBalance($site['baseUrl'], $account['userId'], $account['accessToken'], $site['headerKey']);

        if (!$result['success']) {
            echo "[{$siteName}/{$account['name']}] 查询失败: " . ($result['message'] ?? '未知错误') . "\n";
            continue;
        }

        $remaining = $result['data']['remaining'];
        if ($rate != 1) {
            $remaining *= $rate;
        }

        $unit = ($rate != 1) ? '¥' : '$';
        echo "[{$siteName}/{$account['name']}] 余额: {$unit}" . number_format($remaining, 2) . "\n";

        if ($remaining < $threshold) {
            $alerts[] = [
                'site' => $siteName,
                'name' => $account['name'],
                'remaining' => $remaining,
                'unit' => $unit
            ];
        }
    }
}

if (empty($alerts)) {
    echo "[" . date('Y-m-d H:i:s') . "] 所有账号余额正常，无需告警\n";
    exit(0);
}

// 发送告警邮件
echo "[" . date('Y-m-d H:i:s') . "] 发现 " . count($alerts) . " 个账号低于阈值 ¥{$threshold}，发送告警邮件...\n";

$subject = "【余额预警】" . count($alerts) . " 个账号余额不足";

$body = "以下账号余额低于预警阈值 ¥{$threshold}：\n\n";
foreach ($alerts as $a) {
    $body .= "- {$a['site']} / {$a['name']}：{$a['unit']}" . number_format($a['remaining'], 2) . "\n";
}
$body .= "\n请及时充值。\n\n—— API 余额监控大屏";

$sendResult = sendMail($alert, $subject, $body);

if ($sendResult === true) {
    echo "[" . date('Y-m-d H:i:s') . "] 告警邮件发送成功\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] 告警邮件发送失败: {$sendResult}\n";
    exit(1);
}

// ========== 查询余额（复用 api.php 逻辑） ==========

function queryBalance($baseUrl, $userId, $accessToken, $headerKey) {
    $url = $baseUrl . '/api/user/self';

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
        $headerKey . ': ' . $userId
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        return ['success' => false, 'message' => '请求失败: ' . $error];
    }

    $decoded = @gzdecode($response);
    if ($decoded !== false) {
        $response = $decoded;
    }

    $data = json_decode($response, true);

    if ($data && isset($data['success']) && $data['success']) {
        $quota = $data['data']['quota'] ?? 0;
        $used = $data['data']['used_quota'] ?? 0;

        return [
            'success' => true,
            'data' => [
                'remaining' => $quota / 500000,
                'used' => $used / 500000,
                'total' => ($quota + $used) / 500000
            ]
        ];
    }

    return ['success' => false, 'message' => $data['message'] ?? '查询失败'];
}

// ========== SMTP 发邮件（纯 socket，无需扩展） ==========

function sendMail($alert, $subject, $body) {
    $host = $alert['smtp_host'];
    $port = intval($alert['smtp_port']);
    $user = $alert['smtp_user'];
    $pass = $alert['smtp_password'];
    $to = $alert['email'];

    $errno = 0;
    $errstr = '';
    $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$sock) {
        return "连接 SMTP 失败: {$errstr} ({$errno})";
    }

    if (!smtpRead($sock, '220')) return 'SMTP 未就绪';

    // STARTTLS
    fwrite($sock, "EHLO monitor\r\n");
    if (!smtpRead($sock, '250')) return 'EHLO 失败';

    fwrite($sock, "STARTTLS\r\n");
    if (!smtpRead($sock, '220')) return 'STARTTLS 失败';

    // 升级为加密连接
    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        return 'TLS 握手失败';
    }

    fwrite($sock, "EHLO monitor\r\n");
    if (!smtpRead($sock, '250')) return 'EHLO(2) 失败';

    // AUTH LOGIN
    fwrite($sock, "AUTH LOGIN\r\n");
    if (!smtpRead($sock, '334')) return 'AUTH 请求失败';

    fwrite($sock, base64_encode($user) . "\r\n");
    if (!smtpRead($sock, '334')) return 'AUTH 用户名失败';

    fwrite($sock, base64_encode($pass) . "\r\n");
    if (!smtpRead($sock, '235')) return 'AUTH 密码失败（检查授权码是否正确）';

    fwrite($sock, "MAIL FROM:<{$user}>\r\n");
    if (!smtpRead($sock, '250')) return 'MAIL FROM 失败';

    fwrite($sock, "RCPT TO:<{$to}>\r\n");
    if (!smtpRead($sock, '250')) return 'RCPT TO 失败';

    fwrite($sock, "DATA\r\n");
    if (!smtpRead($sock, '354')) return 'DATA 失败';

    $msg = "From: {$user}\r\n";
    $msg .= "To: {$to}\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "\r\n";
    $msg .= $body . "\r\n";
    $msg .= ".\r\n";

    fwrite($sock, $msg);
    if (!smtpRead($sock, '250')) return '邮件发送失败';

    fwrite($sock, "QUIT\r\n");
    smtpRead($sock, '221');
    fclose($sock);

    return true;
}

function smtpRead($sock, $expectCode) {
    $response = '';
    while ($line = fgets($sock, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    $code = intval(substr($response, 0, 3));
    return $code === intval($expectCode);
}
