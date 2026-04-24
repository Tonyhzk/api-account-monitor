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
