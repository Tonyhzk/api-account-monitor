# 当前项目简介

API 账号余额监控大屏 — 监控多个 New API 兼容平台的 API 账号余额和配额。深色主题看板，卡片式布局，支持多站点多账号、智能进度条、单独刷新、响应式适配。

# 技术栈

- 前端：纯 HTML + CSS（SOMEWHILE 设计语言）+ 原生 JS
- 后端：PHP（API 代理，查询各平台余额接口）
- 配置：`config.json` 管理站点、账号、刷新间隔
- 无框架依赖，无构建步骤

# 核心文件

- `src/api-account-monitor/index.html` — 看板页面及 JS 逻辑
- `src/api-account-monitor/style.css` — 样式
- `src/api-account-monitor/api.php` — 后端 API 代理
- `src/api-account-monitor/config.json` — 账号配置（已在 .gitignore）

# 关键逻辑

- 进度条比例：所有账号最高余额 >= $100 时以此为 100%，否则以 $100 为 100%
- 每张卡片可单独刷新，右上角 ↻ 按钮
- 刷新间隔从 config.json 的 `refreshInterval` 字段读取
- config 中每个账号有 `name`（显示名）和 `account`（登录用户名）两个字段

# 版本追踪

- VERSION — 当前版本号
- CHANGELOG.md / CHANGELOG_CN.md — 更新日志
- README.md / README_CN.md — 项目说明
