# API 账号监控系统

跨多个平台监控 API 密钥、配额和使用情况的系统

[English](README.md) | **中文** | [更新日志](CHANGELOG_CN.md)

---

## 功能特性

### 核心功能
- **多平台支持** - 监控不同提供商的xw API 账号
- **配额追踪** - 实时追踪 API 使用量和配额限制
- **告警系统** - 在接近配额限制或检测到异常时收到通知
- **使用分析** - 可视化 API 使用模式和趋势

---

## 系统要求

| 平台 | 最低版本 |
|------|---------|
| Windows | Windows 10+ |
| macOS | macOS 10.15+ |
| Linux | Ubuntu 20.04+ |

---

## 下载安装

### 从 Releases 下载

从 [Releases](https://github.com/Tonyhzk/api-account-monitor/releases) 页面下载最新版本。

---

## 快速开始

1. 在配置文件中配置您的 API 账号
2. 运行监控应用
3. 在仪表板查看实时监控数据

---

## 配置说明

在配置文件中配置您的 API 账号和监控设置。

---

## 开发指南

### 环境要求

- Python 3.8+
- Node.js 18+（用于仪表板）

### 安装依赖

```bash
pip install -r requirements.txt
```

### 开发命令

```bash
python main.py
```

---

## 项目结构

```
api-account-monitor/
├── src/              # 源代码
├── config/           # 配置文件
├── logs/             # 日志文件
└── README.md
```

---

## 贡献指南

欢迎提交 Pull Request！在提交之前，请确保：

1. 代码通过测试
2. 代码已格式化
3. 提交信息清晰明了

---

## 许可证

[Apache License 2.0](LICENSE)

---

## 作者

**Tonyhzk**

- GitHub: [@Tonyhzk](https://github.com/Tonyhzk)
- 项目地址: [api-account-monitor](https://github.com/Tonyhzk/api-account-monitor)

---

<div align="center">

如果这个项目对你有帮助，欢迎给个 ⭐ Star！

</div>