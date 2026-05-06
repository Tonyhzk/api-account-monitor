<?php
/**
 * OpenCode 适配器 - 查询 OpenCode 用量
 */

function queryOpencodeUsage($serverId, $workspaceId, $authCookie) {
    $args = json_encode([
        't' => ['t' => 9, 'i' => 0, 'l' => 1, 'a' => [['t' => 1, 's' => $workspaceId]], 'o' => 0],
        'f' => 31,
        'm' => []
    ]);
    $url = 'https://opencode.ai/_server?id=' . urlencode($serverId) . '&args=' . urlencode($args);

    $headers = [
        'Accept: */*',
        'Cookie: auth=' . $authCookie . '; oc_locale=zh',
        'Referer: https://opencode.ai/workspace/' . urlencode($workspaceId) . '/usage',
        'X-Server-Id: ' . $serverId,
        'X-Server-Instance: server-fn:3'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    curl_setopt($ch, CURLOPT_PROXY, 'http://127.0.0.1:7897');

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        return ['success' => false, 'message' => '请求失败: ' . $error];
    }

    if (!preg_match('/\{.*mine:.*rollingUsage:.*\}/s', $response, $matches)) {
        return ['success' => false, 'message' => '解析响应失败'];
    }

    $jsData = $matches[0];

    $extractUsage = function($jsData, $type) {
        $pattern = '/' . $type . ':\s*(?:\$R\[\d+\]=)?\{([^}]+)\}/';
        if (!preg_match($pattern, $jsData, $matches)) {
            return null;
        }
        $inner = $matches[1];

        $status = null;
        $resetInSec = null;
        $usagePercent = null;

        if (preg_match('/status:\s*["\x27](\w+)["\x27]/', $inner, $m)) $status = $m[1];
        if (preg_match('/status:\s*(\w+)/', $inner, $m) && !$status) $status = $m[1];
        if (preg_match('/resetInSec:\s*(\d+)/', $inner, $m)) $resetInSec = intval($m[1]);
        if (preg_match('/usagePercent:\s*(\d+)/', $inner, $m)) $usagePercent = intval($m[1]);

        return [
            'status' => $status,
            'resetInSec' => $resetInSec,
            'usagePercent' => $usagePercent
        ];
    };

    $rolling = $extractUsage($jsData, 'rollingUsage');
    $weekly = $extractUsage($jsData, 'weeklyUsage');
    $monthly = $extractUsage($jsData, 'monthlyUsage');

    if (!$rolling && !$weekly && !$monthly) {
        return ['success' => false, 'message' => '无法解析用量数据'];
    }

    $useBalance = preg_match('/useBalance:\s*!0|useBalance:\s*true/', $jsData) ? true : false;
    $mine = preg_match('/mine:\s*!0|mine:\s*true/', $jsData) ? true : false;

    return [
        'success' => true,
        'data' => [
            'type' => 'opencode',
            'mine' => $mine,
            'useBalance' => $useBalance,
            'rollingUsage' => $rolling,
            'weeklyUsage' => $weekly,
            'monthlyUsage' => $monthly
        ]
    ];
}
