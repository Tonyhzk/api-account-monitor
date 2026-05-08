# API 账号余额监控大屏

监控多个 New API 兼容平台的 API 账号余额和配额的看板。

[English](README.md) | **中文** | [更新日志](CHANGELOG_CN.md)

---

## 功能特性

- **多站点监控** - 一个大屏追踪多个 API 平台的账号
- **紧凑卡片** - 每个账号显示站点名、登录用户名、剩余额度、比例进度条
- **智能进度条** - 以最高余额为基准（全部低于 $100 时以 $100 为基准）
- **单独刷新** - 刷新单个账号，无需等待全部
- **自动刷新** - 从配置文件设定刷新间隔
- **多类型账号** - 支持 New API 兼容平台、OpenCode、火山方舟、联通云等账号类型
- **远程配置更新** - 提供带密钥鉴权的配置字段更新接口，便于远程更新登录态或 token
- **响应式布局** - 手机、半屏、全屏自适应
- **深色主题** - 基于黑色的 SOMEWHILE 设计语言

---

## 快速开始

1. 编辑 `src/api-account-monitor/config.json`，填入站点和账号信息
2. 在 `src/api-account-monitor/` 目录启动 PHP 服务器
3. 浏览器打开看板

```bash
cd src/api-account-monitor
php -S localhost:8080
```

---

## 配置说明

编辑源码目录下的 `config.json`：

```json
{
  "refreshInterval": 300,
  "updateToken": "change-this-token",
  "sites": [
    {
      "name": "站点名称",
      "baseUrl": "https://api.example.com",
      "headerKey": "New-Api-User",
      "accounts": [
        {
          "name": "显示名称",
          "account": "登录用户名",
          "userId": "12345",
          "accessToken": "你的token"
        }
      ]
    }
  ]
}
```

`updateToken` 用于远程配置更新接口鉴权，请使用足够长的随机字符串，并避免提交真实配置。

支持的站点类型：

- `newapi`：New API 兼容平台，账号字段为 `name`、`account`、`userId`、`accessToken`。
- `opencode`：OpenCode 用量监控，账号字段为 `name`、`workspaceId`、`authCookie`。
- `volcengine` / `volcengine-afp`：火山方舟用量监控，账号字段为 `name`、`cookies`、`csrfToken`、`webId`。
- `cucloud`：联通云用量监控，账号字段为 `name`、`token`、`accountId`、`tenantId`、`signature`。

### 远程更新配置

接口地址：

```text
POST /api.php?action=update_config
```

请求头二选一：

```text
X-Config-Token: <config.updateToken>
Authorization: Bearer <config.updateToken>
```

单字段更新：

```json
{
  "path": "sites.0.accounts.0.accessToken",
  "value": "new-token"
}
```

批量更新：

```json
{
  "updates": [
    {
      "path": "sites.0.accounts.0.accessToken",
      "value": "new-token"
    },
    {
      "path": "refreshInterval",
      "value": 300
    }
  ]
}
```

路径使用 `config.json` 层级点号写法，数组下标用数字。接口只允许更新已存在字段，无效路径不会写入。

---

## 项目结构

```
api-account-monitor/
├── src/api-account-monitor/
│   ├── index.html      # 看板页面
│   ├── style.css       # 样式（SOMEWHILE 设计语言）
│   ├── api.php         # 后端 API 代理
│   └── config.json     # 账号配置
├── 0_Doc/              # 文档
├── CHANGELOG.md
├── README.md
└── VERSION
```

---

## 许可证

[Apache License 2.0](LICENSE)

---

## 作者

**Tonyhzk**

- GitHub: [@Tonyhzk](https://github.com/Tonyhzk)
- 项目地址: [api-account-monitor](https://github.com/Tonyhzk/api-account-monitor)
