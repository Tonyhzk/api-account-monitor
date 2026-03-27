PHP 启动这个项目的命令如下：

## 方式 1：在项目目录启动（推荐）

```bash
cd /Users/hzk/Documents/HZK-git/api-account-monitor/src/api-account-monitor
php -S localhost:8000
```

## 方式 2：从项目根目录启动

```bash
php -S localhost:8000 -t src/api-account-monitor
```

启动后访问：
- 前端页面：http://localhost:8000/index.html
- API 接口：http://localhost:8000/api.php

## 配置说明

项目已包含必要的文件：
- `api.php` - API 代理接口
- `config.json` - 账号配置文件
- `index.html` - 前端页面（已应用 SOMEWHILE 设计）
- `style.css` - 独立样式文件

## 可选参数

指定端口和主机：
```bash
php -S 0.0.0.0:9000 -t src/api-account-monitor
```

后台运行（macOS/Linux）：
```bash
nohup php -S localhost:8000 -t src/api-account-monitor > server.log 2>&1 &
```

停止服务器：按 `Ctrl+C` 或找到进程 ID 后用 `kill` 命令。