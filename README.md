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
