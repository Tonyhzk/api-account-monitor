<?php
/**
 * 余额监控告警脚本
 * CLI 运行：php monitor.php
 * 检测所有开启 monitor 的账号，低于阈值时发送邮件告警
 *
 * config.json 每个账号可设置 "monitor": true/false 控制是否参与告警
 * 未设置 monitor 字段时默认 true（主页仍显示余额，只是不触发告警邮件）
 */

// 加载所有适配器（复用 api.php 的查询逻辑）
$adaptersDir = __DIR__ . '/adapters/';
foreach (glob($adaptersDir . '*.php') as $adapterFile) {
    require_once $adapterFile;
}

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

foreach ($config['sites'] as $siteIndex => $site) {
    $siteType = $site['type'] ?? 'newapi';
    $siteName = $site['name'];

    foreach ($site['accounts'] as $accountIndex => $account) {
        $accountName = $account['name'];
        $shouldMonitor = $account['monitor'] ?? ($site['monitor'] ?? false);

        // 未显式开启 monitor 的账号，直接跳过
        if (!$shouldMonitor) {
            continue;
        }

        $result = dispatchMonitorQuery($site, $account);

        if (!$result['success']) {
            echo "[{$siteName}/{$accountName}] 查询失败: " . ($result['message'] ?? '未知错误') . "\n";
            continue;
        }

        // 根据类型格式化日志输出
        $logLine = formatLogLine($siteType, $siteName, $accountName, $result, $site);
        echo $logLine . "\n";

        // 按类型判断是否需要告警
        $alertInfo = checkAlert($siteType, $result, $site, $threshold);
        if ($alertInfo) {
            $alertInfo['site'] = $siteName;
            $alertInfo['name'] = $accountName;
            $alerts[] = $alertInfo;
        }
    }
}

if (empty($alerts)) {
    echo "[" . date('Y-m-d H:i:s') . "] 所有账号状态正常，无需告警\n";
    exit(0);
}

// 发送告警邮件
echo "[" . date('Y-m-d H:i:s') . "] 发现 " . count($alerts) . " 个账号低于阈值，发送告警邮件...\n";

$subject = "【余额预警】" . count($alerts) . " 个账号余额不足";

$body = "以下账号余额低于预警阈值：\n\n";
foreach ($alerts as $a) {
    $body .= "- {$a['site']} / {$a['name']}：{$a['unit']}" . number_format($a['remaining'], 2);
    if (!empty($a['extra'])) {
        $body .= " ({$a['extra']})";
    }
    $body .= "\n";
}
$body .= "\n请及时处理。\n\n—— API 余额监控大屏";

$maxRetries = 3;
for ($i = 1; $i <= $maxRetries; $i++) {
    $sendResult = sendMail($alert, $subject, $body);

    if ($sendResult === true) {
        echo "[" . date('Y-m-d H:i:s') . "] 告警邮件发送成功\n";
        exit(0);
    }

    echo "[" . date('Y-m-d H:i:s') . "] 告警邮件发送失败 (第{$i}次): {$sendResult}\n";
    if ($i < $maxRetries) {
        sleep(5);
    }
}

echo "[" . date('Y-m-d H:i:s') . "] 告警邮件重试 {$maxRetries} 次均失败，本次跳过\n";
exit(0);

// ========== 查询分发（复用 adapters） ==========

function dispatchMonitorQuery($site, $account) {
    global $config;
    $siteType = $site['type'] ?? 'newapi';
    $proxy = $config['proxy'] ?? null;

    switch ($siteType) {
        case 'opencode':
            return queryOpencodeUsage($site['serverId'], $account['workspaceId'], $account['authCookie'], $proxy);

        case 'volcengine':
            return queryVolcengineUsage($account['cookies'], $account['csrfToken'], $account['webId']);

        case 'volcengine-afp':
            return queryVolcengineAfpUsage($account['cookies'], $account['csrfToken'], $account['webId']);

        case 'cucloud':
            return queryCucloudUsage($account['token'], $account['accountId'], $account['tenantId'], $account['signature']);

        case 'newapi':
        default:
            $rate = floatval($site['rate'] ?? 1);
            return queryNewapiBalance($site['baseUrl'], $account['userId'], $account['accessToken'], $site['headerKey'], $rate);
    }
}

// ========== 日志格式化 ==========

function formatLogLine($siteType, $siteName, $accountName, $result, $site) {
    $data = $result['data'];

    switch ($siteType) {
        case 'newapi':
            $rate = floatval($site['rate'] ?? 1);
            $unit = ($rate != 1) ? '¥' : '$';
            $remaining = $data['remaining'];
            return "[{$siteName}/{$accountName}] 余额: {$unit}" . number_format($remaining, 2);

        case 'opencode':
            $rolling = $data['rollingUsage']['usagePercent'] ?? '?';
            $weekly = $data['weeklyUsage']['usagePercent'] ?? '?';
            $monthly = $data['monthlyUsage']['usagePercent'] ?? '?';
            return "[{$siteName}/{$accountName}] 用量: 滚动{$rolling}% / 周{$weekly}% / 月{$monthly}%";

        case 'volcengine':
            $session = $data['sessionUsage']['usagePercent'] ?? '?';
            $weekly = $data['weeklyUsage']['usagePercent'] ?? '?';
            $monthly = $data['monthlyUsage']['usagePercent'] ?? '?';
            return "[{$siteName}/{$accountName}] 用量: 会话{$session}% / 周{$weekly}% / 月{$monthly}%";

        case 'volcengine-afp':
            $daily = $data['dailyUsage']['usagePercent'] ?? '?';
            $fiveHour = $data['fiveHourUsage']['usagePercent'] ?? '?';
            $weekly = $data['weeklyUsage']['usagePercent'] ?? '?';
            $monthly = $data['monthlyUsage']['usagePercent'] ?? '?';
            return "[{$siteName}/{$accountName}] 用量: 日{$daily}% / 5h{$fiveHour}% / 周{$weekly}% / 月{$monthly}%";

        case 'cucloud':
            // 多套餐时列出每个
            if (isset($data['packages'])) {
                $lines = "[{$siteName}/{$accountName}] 多套餐:";
                foreach ($data['packages'] as $pkg) {
                    $monthPct = $pkg['perMonth']['usagePercent'] ?? '?';
                    $lines .= " [{$pkg['packageName']} 月{$monthPct}%]";
                }
                return $lines;
            }
            $session = $data['sessionUsage']['usagePercent'] ?? '?';
            $weekly = $data['weeklyUsage']['usagePercent'] ?? '?';
            $monthly = $data['monthlyUsage']['usagePercent'] ?? '?';
            return "[{$siteName}/{$accountName}] 用量: 5h{$session}% / 周{$weekly}% / 月{$monthly}%";

        default:
            return "[{$siteName}/{$accountName}] 查询完成";
    }
}

// ========== 告警判断 ==========

function checkAlert($siteType, $result, $site, $threshold) {
    $data = $result['data'];

    switch ($siteType) {
        case 'newapi':
            $rate = floatval($site['rate'] ?? 1);
            $unit = ($rate != 1) ? '¥' : '$';
            $remaining = $data['remaining'];
            if ($remaining < $threshold) {
                return [
                    'remaining' => $remaining,
                    'unit' => $unit,
                    'extra' => "阈值 {$unit}{$threshold}"
                ];
            }
            return null;

        case 'opencode':
            $monthly = $data['monthlyUsage']['usagePercent'] ?? 0;
            if (is_numeric($monthly) && $monthly >= 90) {
                return [
                    'remaining' => 100 - $monthly,
                    'unit' => '%',
                    'extra' => "月用量{$monthly}%"
                ];
            }
            return null;

        case 'volcengine':
            $monthly = $data['monthlyUsage']['usagePercent'] ?? 0;
            if (is_numeric($monthly) && $monthly >= 90) {
                return [
                    'remaining' => 100 - $monthly,
                    'unit' => '%',
                    'extra' => "月用量{$monthly}%"
                ];
            }
            return null;

        case 'volcengine-afp':
            $monthly = $data['monthlyUsage']['usagePercent'] ?? 0;
            if (is_numeric($monthly) && $monthly >= 90) {
                return [
                    'remaining' => 100 - $monthly,
                    'unit' => '%',
                    'extra' => "月用量{$monthly}%"
                ];
            }
            return null;

        case 'cucloud':
            // 多套餐时检查每个套餐的月用量
            if (isset($data['packages'])) {
                foreach ($data['packages'] as $pkg) {
                    $monthly = $pkg['perMonth']['usagePercent'] ?? 0;
                    if (is_numeric($monthly) && $monthly >= 90) {
                        return [
                            'remaining' => 100 - $monthly,
                            'unit' => '%',
                            'extra' => "{$pkg['packageName']} 月用量{$monthly}%"
                        ];
                    }
                }
                return null;
            }
            $monthly = $data['monthlyUsage']['usagePercent'] ?? 0;
            if (is_numeric($monthly) && $monthly >= 90) {
                return [
                    'remaining' => 100 - $monthly,
                    'unit' => '%',
                    'extra' => "月用量{$monthly}%"
                ];
            }
            return null;

        default:
            return null;
    }
}

// ========== SMTP 发邮件（纯 socket，无需扩展） ==========

function sendMail($alert, $subject, $body) {
    $host = $alert['smtp_host'];
    $port = intval($alert['smtp_port']);
    $user = $alert['smtp_user'];
    $pass = $alert['smtp_password'];
    $to = $alert['email'];
    $isSmtps = ($port === 465);

    $errno = 0;
    $errstr = '';

    if ($isSmtps) {
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $sock = @stream_socket_client("tls://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
    } else {
        $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    }

    if (!$sock) {
        return "连接 SMTP 失败: {$errstr} ({$errno})";
    }

    if (!smtpRead($sock, '220')) return 'SMTP 未就绪';

    fwrite($sock, "EHLO monitor\r\n");
    if (!smtpRead($sock, '250')) return 'EHLO 失败';

    if (!$isSmtps) {
        fwrite($sock, "STARTTLS\r\n");
        if (!smtpRead($sock, '220')) return 'STARTTLS 失败';

        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return 'TLS 握手失败';
        }

        fwrite($sock, "EHLO monitor\r\n");
        if (!smtpRead($sock, '250')) return 'EHLO(2) 失败';
    }

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
    $msg .= "Date: " . date('r') . "\r\n";
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
