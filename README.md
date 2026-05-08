# API Account Monitor

A dashboard for monitoring API account balances and quotas across multiple New API compatible platforms.

[English](README.md) | **中文** | [Changelog](CHANGELOG.md)

---

## Features

- **Multi-site Monitoring** - Track accounts across different API providers in one dashboard
- **Compact Card View** - Each account shows site name, login username, remaining balance, and a proportional progress bar
- **Smart Progress Bar** - Bars scale relative to the highest balance (or $100 if all below $100)
- **Single Card Refresh** - Refresh individual accounts without reloading all
- **Auto Refresh** - Configurable refresh interval from config file
- **Multiple Account Types** - Supports New API compatible platforms, OpenCode, Volcengine Ark, China Unicom Cloud, and more
- **Remote Config Updates** - Provides a token-protected endpoint for updating specific config fields remotely
- **Responsive Layout** - Works on phone, half-screen, and full-width displays
- **Dark Theme** - Black-based SOMEWHILE design language

---

## Quick Start

1. Edit `src/api-account-monitor/config.json` with your sites and accounts
2. Start a PHP server in `src/api-account-monitor/`
3. Open the dashboard in a browser

```bash
cd src/api-account-monitor
php -S localhost:8080
```

---

## Configuration

See `config.json` in the source directory:

```json
{
  "refreshInterval": 300,
  "updateToken": "change-this-token",
  "sites": [
    {
      "name": "Site Name",
      "baseUrl": "https://api.example.com",
      "headerKey": "New-Api-User",
      "accounts": [
        {
          "name": "Display Name",
          "account": "login_username",
          "userId": "12345",
          "accessToken": "your_token"
        }
      ]
    }
  ]
}
```

`updateToken` protects the remote config update endpoint. Use a long random string and never commit your real config.

Supported site types:

- `newapi`: New API compatible platforms. Account fields: `name`, `account`, `userId`, `accessToken`.
- `opencode`: OpenCode usage monitoring. Account fields: `name`, `workspaceId`, `authCookie`.
- `volcengine` / `volcengine-afp`: Volcengine Ark usage monitoring. Account fields: `name`, `cookies`, `csrfToken`, `webId`.
- `cucloud`: China Unicom Cloud usage monitoring. Account fields: `name`, `token`, `accountId`, `tenantId`, `signature`.

### Remote Config Updates

Endpoint:

```text
POST /api.php?action=update_config
```

Use one of these headers:

```text
X-Config-Token: <config.updateToken>
Authorization: Bearer <config.updateToken>
```

Single-field update:

```json
{
  "path": "sites.0.accounts.0.accessToken",
  "value": "new-token"
}
```

Batch update:

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

Paths use dot notation matching `config.json`; array indexes are numeric. The endpoint only updates existing fields, and invalid paths are rejected without writing changes.

---

## Project Structure

```
api-account-monitor/
├── src/api-account-monitor/
│   ├── index.html      # Dashboard page
│   ├── style.css       # Styles (SOMEWHILE design)
│   ├── api.php         # Backend API proxy
│   └── config.json     # Account configuration
├── 0_Doc/              # Documentation
├── CHANGELOG.md
├── README.md
└── VERSION
```

---

## License

[Apache License 2.0](LICENSE)

---

## Author

**Tonyhzk**

- GitHub: [@Tonyhzk](https://github.com/Tonyhzk)
- Project: [api-account-monitor](https://github.com/Tonyhzk/api-account-monitor)
