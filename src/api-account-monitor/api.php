<?php
/**
 * API 代理接口 - 用于查询账号余额
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 读取配置文件
$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    echo json_encode(['success' => false, 'message' => '配置文件不存在']);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);

// 处理请求
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_config':
        // 返回配置信息（不包含敏感信息，但包含账号ID用于后续查询）
        $publicConfig = [
            'refreshInterval' => $config['refreshInterval'] ?? 300,
            'sites' => array_map(function($siteIndex, $site) {
                return [
                    'name' => $site['name'],
                    'baseUrl' => $site['baseUrl'],
                    'accounts' => array_map(function($accountIndex, $account) use ($siteIndex) {
                        return [
                            'name' => $account['name'],
                            'siteIndex' => $siteIndex,
                            'accountIndex' => $accountIndex
                        ];
                    }, array_keys($site['accounts']), $site['accounts'])
                ];
            }, array_keys($config['sites']), $config['sites'])
        ];
        echo json_encode(['success' => true, 'data' => $publicConfig]);
        break;

    case 'query_balance':
        // 查询单个账号余额
        $siteIndex = intval($_GET['site'] ?? -1);
        $accountIndex = intval($_GET['account'] ?? -1);

        if ($siteIndex < 0 || $accountIndex < 0 ||
            !isset($config['sites'][$siteIndex]) ||
            !isset($config['sites'][$siteIndex]['accounts'][$accountIndex])) {
            echo json_encode(['success' => false, 'message' => '账号不存在']);
            exit;
        }

        $site = $config['sites'][$siteIndex];
        $account = $site['accounts'][$accountIndex];

        $result = queryBalance($site['baseUrl'], $account['userId'], $account['accessToken'], $site['headerKey']);
        echo json_encode($result);
        break;

    case 'query_all':
        // 查询所有账号余额
        $results = [];

        foreach ($config['sites'] as $siteIndex => $site) {
            foreach ($site['accounts'] as $accountIndex => $account) {
                $result = queryBalance($site['baseUrl'], $account['userId'], $account['accessToken'], $site['headerKey']);
                $results[] = [
                    'siteName' => $site['name'],
                    'accountName' => $account['name'],
                    'result' => $result
                ];

                // 避免请求过快
                usleep(200000); // 200ms
            }
        }

        echo json_encode(['success' => true, 'data' => $results]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
}

/**
 * 查询账号余额
 */
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
    curl_setopt($ch, CURLOPT_ENCODING, ''); // 自动处理 gzip 压缩

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    // curl_close() 在 PHP 8.0+ 已无实际作用，PHP 8.5+ 已弃用

    if ($error) {
        return ['success' => false, 'message' => '请求失败: ' . $error];
    }

    // 尝试解压 gzip 数据（如果需要）
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
                'planName' => $data['data']['group'] ?? '默认套餐',
                'remaining' => $quota / 500000,
                'used' => $used / 500000,
                'total' => ($quota + $used) / 500000,
                'unit' => 'USD'
            ]
        ];
    }

    return [
        'success' => false,
        'message' => $data['message'] ?? '查询失败'
    ];
}
?>