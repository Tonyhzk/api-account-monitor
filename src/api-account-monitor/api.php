<?php
/**
 * API 代理接口 - 路由分发
 * 每种渠道类型对应 adapters/ 下的一个适配器文件
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 加载所有适配器
$adaptersDir = __DIR__ . '/adapters/';
foreach (glob($adaptersDir . '*.php') as $adapterFile) {
    require_once $adapterFile;
}

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
        $publicConfig = [
            'refreshInterval' => $config['refreshInterval'] ?? 300,
            'sites' => array_map(function($siteIndex, $site) {
                return [
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
            }, array_keys($config['sites']), $config['sites'])
        ];
        echo json_encode(['success' => true, 'data' => $publicConfig]);
        break;

    case 'query_balance':
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
        $result = dispatchQuery($site, $account);

        echo json_encode($result);
        break;

    case 'query_all':
        $results = [];

        foreach ($config['sites'] as $siteIndex => $site) {
            foreach ($site['accounts'] as $accountIndex => $account) {
                $result = dispatchQuery($site, $account);

                $results[] = [
                    'siteName' => $site['name'],
                    'accountName' => $account['name'],
                    'result' => $result
                ];

                usleep(200000);
            }
        }

        echo json_encode(['success' => true, 'data' => $results]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
}

/**
 * 根据站点类型分发到对应适配器
 */
function dispatchQuery($site, $account) {
    global $config;
    $siteType = $site['type'] ?? 'newapi';
    $proxy = $config['proxy'] ?? null;

    switch ($siteType) {
        case 'opencode':
            return queryOpencodeUsage($site['serverId'], $account['workspaceId'], $account['authCookie'], $proxy);

        case 'volcengine':
            return queryVolcengineUsage($account['cookies'], $account['csrfToken'], $account['webId']);

        case 'cucloud':
            return queryCucloudUsage($account['token'], $account['accountId'], $account['tenantId'], $account['signature']);

        case 'newapi':
        default:
            $rate = floatval($site['rate'] ?? 1);
            return queryNewapiBalance($site['baseUrl'], $account['userId'], $account['accessToken'], $site['headerKey'], $rate);
    }
}
