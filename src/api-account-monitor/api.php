<?php
/**
 * API 代理接口 - 路由分发
 * 每种渠道类型对应 adapters/ 下的一个适配器文件
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Config-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

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

    case 'update_config':
        handleConfigUpdate($config, $configFile);
        break;

    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
}

function handleConfigUpdate(&$config, $configFile) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '只支持 POST 请求']);
        return;
    }

    $configuredToken = $config['updateToken'] ?? '';
    if ($configuredToken === '') {
        echo json_encode(['success' => false, 'message' => '未配置 updateToken']);
        return;
    }

    $token = $_SERVER['HTTP_X_CONFIG_TOKEN'] ?? '';
    if ($token === '') {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            $token = $matches[1];
        }
    }

    if (!hash_equals($configuredToken, $token)) {
        echo json_encode(['success' => false, 'message' => '更新密钥错误']);
        return;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        echo json_encode(['success' => false, 'message' => '请求体必须是 JSON 对象']);
        return;
    }

    $updates = $payload['updates'] ?? null;
    if ($updates === null && isset($payload['path'])) {
        $updates = [[
            'path' => $payload['path'],
            'value' => $payload['value'] ?? null
        ]];
    }

    if (!is_array($updates) || count($updates) === 0) {
        echo json_encode(['success' => false, 'message' => 'updates 不能为空']);
        return;
    }

    $nextConfig = $config;
    foreach ($updates as $update) {
        if (!is_array($update) || !isset($update['path'])) {
            echo json_encode(['success' => false, 'message' => '每条更新必须包含 path']);
            return;
        }

        $path = $update['path'];
        if (!is_string($path) || $path === '') {
            echo json_encode(['success' => false, 'message' => 'path 必须是非空字符串']);
            return;
        }

        if (!array_key_exists('value', $update)) {
            echo json_encode(['success' => false, 'message' => "缺少 value: {$path}"]);
            return;
        }

        if (!setConfigValue($nextConfig, $path, $update['value'])) {
            echo json_encode(['success' => false, 'message' => "配置路径不存在: {$path}"]);
            return;
        }
    }

    $json = json_encode($nextConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        echo json_encode(['success' => false, 'message' => '配置序列化失败']);
        return;
    }

    $lockFile = $configFile . '.lock';
    $lockHandle = fopen($lockFile, 'c');
    if (!$lockHandle || !flock($lockHandle, LOCK_EX)) {
        echo json_encode(['success' => false, 'message' => '无法获取配置写入锁']);
        if ($lockHandle) fclose($lockHandle);
        return;
    }

    $tmpFile = $configFile . '.tmp';
    $writeOk = file_put_contents($tmpFile, $json . PHP_EOL, LOCK_EX) !== false
        && rename($tmpFile, $configFile);

    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);

    if (!$writeOk) {
        if (file_exists($tmpFile)) unlink($tmpFile);
        echo json_encode(['success' => false, 'message' => '写入配置失败']);
        return;
    }

    $config = $nextConfig;
    echo json_encode(['success' => true, 'message' => '配置已更新']);
}

function setConfigValue(&$config, $path, $value) {
    $segments = explode('.', $path);
    $current = &$config;

    foreach ($segments as $index => $segment) {
        if ($segment === '') return false;
        $key = ctype_digit($segment) ? intval($segment) : $segment;
        $isLast = $index === count($segments) - 1;

        if (!is_array($current) || !array_key_exists($key, $current)) {
            return false;
        }

        if ($isLast) {
            $current[$key] = $value;
            return true;
        }

        $current = &$current[$key];
    }

    return false;
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
