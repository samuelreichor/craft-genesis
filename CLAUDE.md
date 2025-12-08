# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Genesis is a Craft CMS 5 plugin for bulk importing Craft elements via CSV. It requires Craft CMS 5.8.0+ and PHP 8.2+.

## Commands

```bash
# Code style check
composer check-cs

# Code style fix
composer fix-cs

# Static analysis
composer phpstan
```

## Architecture

This is a standard Craft CMS plugin following Craft's plugin architecture:

- **Entry point**: `src/Genesis.php` - Main plugin class that registers components and event handlers
- **Utilities**: `src/utilities/` - Craft utilities registered in the Control Panel
- **Namespace**: `samuelreichor\genesis`

The plugin registers a custom utility (`ImportUtil`) in the Craft Control Panel and maintains its own log file at `@storage/logs/genesis.log`.

## Code Style

Uses Craft CMS ECS (Easy Coding Standard) with the `SetList::CRAFT_CMS_4` ruleset. Configuration in `ecs.php`.
