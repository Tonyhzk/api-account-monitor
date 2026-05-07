<?php
/**
 * 火山方舟 Agent Plan AFP 适配器 - 通过控制台 Cookie 接口查询用量
 *
 * 接口: POST https://console.volcengine.com/api/top/ark/cn-beijing/2024-01-01/GetAgentPlanAFPUsage
 * 认证: Cookie + CSRF Token
 * 返回: daily/fiveHour/weekly/monthly 四条用量及重置时间
 */

function queryVolcengineAfpUsage($cookies, $csrfToken, $webId) {
    $url = 'https://console.volcengine.com/api/top/ark/cn-beijing/2024-01-01/GetAgentPlanAFPUsage';

    $headers = [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'Cookie: ' . $cookies,
        'Origin: https://console.volcengine.com',
        'Referer: https://console.volcengine.com/ark/region:ark+cn-beijing/openManagement',
        'X-CSRF-Token: ' . $csrfToken,
        'X-Web-Id: ' . $webId,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if ($error) {
        return ['success' => false, 'message' => '请求失败: ' . $error];
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['Result'])) {
        $errMsg = $data['ResponseMetadata']['Error']['Message'] ?? '查询失败';
        return ['success' => false, 'message' => $errMsg];
    }

    $result = $data['Result'];
    $now = time();

    $calcUsage = function($item) use ($now) {
        if (!$item) return null;
        $quota = floatval($item['Quota'] ?? 0);
        $used = floatval($item['Used'] ?? 0);
        $resetTimestamp = intval(($item['ResetTime'] ?? 0) / 1000);
        $percent = $quota > 0 ? ($used / $quota) * 100 : 0;
        $resetInSec = $resetTimestamp > 0 ? max($resetTimestamp - $now, 0) : null;

        return [
            'usagePercent' => round($percent, 2),
            'used' => $used,
            'quota' => $quota,
            'resetInSec' => $resetInSec,
        ];
    };

    return [
        'success' => true,
        'data' => [
            'type' => 'volcengine-afp',
            'planType' => $result['PlanType'] ?? 'unknown',
            'dailyUsage' => $calcUsage($result['AFPDaily'] ?? null),
            'fiveHourUsage' => $calcUsage($result['AFPFiveHour'] ?? null),
            'weeklyUsage' => $calcUsage($result['AFPWeekly'] ?? null),
            'monthlyUsage' => $calcUsage($result['AFPMonthly'] ?? null),
        ]
    ];
}
