<?php
/**
 * 火山方舟 Coding Plan 适配器 - 通过控制台 Cookie 接口查询用量
 *
 * 接口: POST https://console.volcengine.com/api/top/ark/cn-beijing/2024-01-01/GetCodingPlanUsage
 * 认证: Cookie + CSRF Token
 * 返回: session/weekly/monthly 三条用量百分比及重置时间戳
 */

function queryVolcengineUsage($cookies, $csrfToken, $webId) {
    $url = 'https://console.volcengine.com/api/top/ark/cn-beijing/2024-01-01/GetCodingPlanUsage';

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
    $quotaUsage = $result['QuotaUsage'] ?? [];

    $session = null;
    $weekly = null;
    $monthly = null;

    foreach ($quotaUsage as $item) {
        $level = $item['Level'] ?? '';
        $usageData = [
            'percent' => floatval($item['Percent'] ?? 0),
            'resetTimestamp' => intval($item['ResetTimestamp'] ?? 0),
        ];

        if ($level === 'session') $session = $usageData;
        elseif ($level === 'weekly') $weekly = $usageData;
        elseif ($level === 'monthly') $monthly = $usageData;
    }

    // 把时间戳转为剩余秒数
    $now = time();
    $calcResetSec = function($resetTimestamp) use ($now) {
        if (!$resetTimestamp) return null;
        $sec = $resetTimestamp - $now;
        return $sec > 0 ? $sec : 0;
    };

    return [
        'success' => true,
        'data' => [
            'type' => 'volcengine',
            'status' => $result['Status'] ?? 'unknown',
            'sessionUsage' => $session ? [
                'usagePercent' => $session['percent'],
                'resetInSec' => $calcResetSec($session['resetTimestamp']),
            ] : null,
            'weeklyUsage' => $weekly ? [
                'usagePercent' => $weekly['percent'],
                'resetInSec' => $calcResetSec($weekly['resetTimestamp']),
            ] : null,
            'monthlyUsage' => $monthly ? [
                'usagePercent' => $monthly['percent'],
                'resetInSec' => $calcResetSec($monthly['resetTimestamp']),
            ] : null,
        ]
    ];
}
