# Changelog

All notable changes to this project will be documented in this file.

**English** | [中文](CHANGELOG_CN.md)

---

## [1.1.0] - 2026-04-24

### Changed

- **Compact card layout** - Removed plan name, used quota, total quota; only remaining balance shown
- **Smart progress bar** - Bar scaled to max balance across all accounts (or $100 if all below $100)
- **Added `account` field** - Each account in config now has an `account` field for login username, displayed on card

### Added

- **Single card refresh** - Each card has a refresh button to reload only that account
- **Responsive grid** - Cards auto-fill from 240px minimum, works on phone / half-screen / full-width

## [1.0.0] - 2026-03-27

### Added

- **Config-driven refresh interval** - Read `refreshInterval` from `config.json`, auto-select matching option or add custom one
- **PHP setup guide** - Added `PHP启动说明.md` for local PHP server

## [0.0.1] - 2026-03-27

### First Release

- Multi-platform API account monitoring dashboard
- Real-time quota tracking with progress bars
- Auto-refresh with configurable interval
- Dark theme dashboard UI
