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
                $siteData = [
                    'name' => $site['name'],
                    'type' => $site['type'] ?? 'newapi',
                    'baseUrl' => $site['baseUrl'] ?? '',
                    'accounts' => array_map(function($accountIndex, $account) use ($siteIndex) {
                        return [
                            'name' => $account['name'],
                            'account' => $account['account'] ?? '',
                            'siteIndex' => $siteIndex,
                            'accountIndex' => $accountIndex
                        ];
                    }, array_keys($site['accounts']), $site['accounts'])
                ];
                return $siteData;
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
        $siteType = $site['type'] ?? 'newapi';

        if ($siteType === 'opencode') {
            $result = queryOpencodeUsage($site['serverId'], $account['workspaceId'], $account['authCookie']);
        } else {
            $rate = floatval($site['rate'] ?? 1);
            $result = queryBalance($site['baseUrl'], $account['userId'], $account['accessToken'], $site['headerKey']);

            if ($result['success'] && $rate != 1) {
                $result['data']['remaining'] *= $rate;
                $result['data']['used'] *= $rate;
                $result['data']['total'] *= $rate;
                $result['data']['unit'] = 'CNY';
            }
        }

        echo json_encode($result);
        break;

    case 'query_all':
        // 查询所有账号余额
        $results = [];

        foreach ($config['sites'] as $siteIndex => $site) {
            $siteType = $site['type'] ?? 'newapi';
            foreach ($site['accounts'] as $accountIndex => $account) {
                if ($siteType === 'opencode') {
                    $result = queryOpencodeUsage($site['serverId'], $account['workspaceId'], $account['authCookie']);
                } else {
                    $rate = floatval($site['rate'] ?? 1);
                    $result = queryBalance($site['baseUrl'], $account['userId'], $account['accessToken'], $site['headerKey']);

                    if ($result['success'] && $rate != 1) {
                        $result['data']['remaining'] *= $rate;
                        $result['data']['used'] *= $rate;
                        $result['data']['total'] *= $rate;
                        $result['data']['unit'] = 'CNY';
                    }
                }

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
 * 查询 OpenCode 用量
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

    // 响应格式: ;0x00000127;((self.$R=self.$R||{})["server-fn:3"]=[],($R=>$R[0]={mine:!0,useBalance:!1,rollingUsage:$R[1]={status:"ok",resetInSec:14519,usagePercent:0},weeklyUsage:$R[2]={status:"ok",resetInSec:397218,usagePercent:96},monthlyUsage:$R[3]={status:"ok",resetInSec:1845610,usagePercent:98}})($R["server-fn:3"]))
    // 提取 JSON 数据
    if (!preg_match('/\{.*mine:.*rollingUsage:.*\}/s', $response, $matches)) {
        return ['success' => false, 'message' => '解析响应失败'];
    }

    $jsData = $matches[0];

    // 解析 key:value 格式（非标准 JSON）
    $parseField = function($jsData, $field) {
        $pattern = '/' . $field . ':(["\x27]?)([^,}\]]*?)\1[,\]}\s]/';
        if (preg_match_all($pattern, $jsData, $matches, PREG_SET_ORDER)) {
            return $matches[0][2];
        }
        return null;
    };

    // 用更可靠的方式提取嵌套结构
    $extractUsage = function($jsData, $type) {
        // 匹配 rollingUsage:$R[1]={...} 或 rollingUsage:{status:"ok",...}
        // 实际响应格式为 rollingUsage:$R[N]={...}，$R[N]= 是可选前缀
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

    // 检测 useBalance 和 mine 标记
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