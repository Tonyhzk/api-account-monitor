<?php
/**
 * 联通云 CUCloud Coding Plan 适配器
 *
 * 接口: GET https://gateway.cucloud.cn/cmp-cuig/v1/package?{signature}
 * 认证: Bearer Token + AccountID + TenantID
 * 返回: 套餐列表，每个套餐含 perFiveHour/perWeek/perMonth 用量及重置时间
 */

function queryCucloudUsage($token, $accountId, $tenantId, $signature) {
    $url = 'https://gateway.cucloud.cn/cmp-cuig/v1/package?' . $signature;

    $headers = [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json',
        'Access_token: ' . $token,
        'Accountid: ' . $accountId,
        'Authorization: Bearer ' . $token,
        'Charset: utf-8',
        'Origin: https://console.cucloud.cn',
        'Referer: https://console.cucloud.cn/',
        'Resourcetype: 9316',
        'Tenantid: ' . $tenantId,
        'Token: ' . $token,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    if ($error) {
        return ['success' => false, 'message' => '请求失败: ' . $error];
    }

    $data = json_decode($response, true);

    if (!$data || ($data['code'] ?? 0) != 200) {
        return ['success' => false, 'message' => $data['message'] ?? '查询失败'];
    }

    $packages = $data['data'] ?? [];
    if (empty($packages)) {
        return ['success' => false, 'message' => '无套餐数据'];
    }

    $results = [];
    foreach ($packages as $pkg) {
        $detail = $pkg['usageDetail'] ?? [];
        $pkgInfo = [
            'packageName' => $pkg['packageName'] ?? '未知套餐',
            'status' => $pkg['status'] ?? 'unknown',
            'apiKey' => $pkg['apiKey'] ?? '',
            'baseUrl' => $pkg['baseUrl'] ?? '',
        ];

        // 把各窗口数据转为统一格式
        $parseWindow = function($window) {
            if (!$window) return null;
            $now = time();
            $resetInSec = 0;
            if (!empty($window['refreshTime'])) {
                $resetTs = strtotime($window['refreshTime']);
                if ($resetTs) $resetInSec = max(0, $resetTs - $now);
            }
            return [
                'usagePercent' => floatval($window['ratio'] ?? 0),
                'used' => intval($window['usage'] ?? 0),
                'total' => intval($window['total'] ?? 0),
                'resetInSec' => $resetInSec,
                'refreshTime' => $window['refreshTime'] ?? '',
            ];
        };

        $pkgInfo['perFiveHour'] = $parseWindow($detail['perFiveHour'] ?? null);
        $pkgInfo['perWeek'] = $parseWindow($detail['perWeek'] ?? null);
        $pkgInfo['perMonth'] = $parseWindow($detail['perMonth'] ?? null);

        $results[] = $pkgInfo;
    }

    // 如果只有一个套餐，直接展开到顶层
    if (count($results) === 1) {
        $r = $results[0];
        return [
            'success' => true,
            'data' => [
                'type' => 'cucloud',
                'packageName' => $r['packageName'],
                'status' => $r['status'],
                'apiKey' => $r['apiKey'],
                'baseUrl' => $r['baseUrl'],
                'sessionUsage' => $r['perFiveHour'],
                'weeklyUsage' => $r['perWeek'],
                'monthlyUsage' => $r['perMonth'],
            ]
        ];
    }

    // 多套餐时返回列表
    return [
        'success' => true,
        'data' => [
            'type' => 'cucloud',
            'packages' => $results,
        ]
    ];
}
