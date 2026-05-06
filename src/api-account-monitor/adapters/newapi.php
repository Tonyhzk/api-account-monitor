<?php
/**
 * New API 适配器 - 查询 New API 兼容平台余额
 */

function queryNewapiBalance($baseUrl, $userId, $accessToken, $headerKey, $rate = 1) {
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

        $remaining = $quota / 500000;
        $usedAmount = $used / 500000;
        $total = ($quota + $used) / 500000;
        $unit = 'USD';

        if ($rate != 1) {
            $remaining *= $rate;
            $usedAmount *= $rate;
            $total *= $rate;
            $unit = 'CNY';
        }

        return [
            'success' => true,
            'data' => [
                'type' => 'newapi',
                'planName' => $data['data']['group'] ?? '默认套餐',
                'remaining' => $remaining,
                'used' => $usedAmount,
                'total' => $total,
                'unit' => $unit
            ]
        ];
    }

    return [
        'success' => false,
        'message' => $data['message'] ?? '查询失败'
    ];
}
