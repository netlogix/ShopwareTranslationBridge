# Shopware Translation Bridge

This plugin provides a bridge to connect Shopware with any translation provider supported by the [Symfony Translation Component](https://symfony.com/doc/current/translation.html#translation-providers). It lets you manage your storefront snippets through a third-party translation service (e.g. Tolgee, Crowdin, Lokalise) instead of maintaining them by hand in the administration.

At its core the plugin does two things: it applies provider translations to the storefront at runtime, and it provides CLI commands and an API to synchronise snippets between Shopware and the provider in both directions.

## Requirements

* PHP 8.3+
* Shopware 6.7.1 or higher (`shopware/core` and `shopware/storefront`)
* `symfony/translation` 7.x

## Installation

```bash
composer require netlogix/shopware-translation-bridge
bin/console plugin:install --activate ShopwareTranslationBridge
```

## Configuration

The Symfony translation providers themselves (their DSNs) are configured the usual Symfony way, e.g. in `config/packages/translation.yaml` under `framework.translator.providers`. The service name you give a provider there (for example `tolgee`) is the value you reference in the plugin settings.

Everything specific to this plugin is configured as regular **Shopware plugin settings** — no extra config file needed. Open Administration → Extensions → My extensions → Translation Bridge → Config:

| Field                           | Type     | Default | Description                                                                                              |
|---------------------------------|----------|---------|----------------------------------------------------------------------------------------------------------|
| Default translation provider    | `string` | empty   | Service name from `framework.translator.providers` (e.g. `tolgee`). Empty means no provider is used.     |
| Respect local translation files | `bool`   | `true`  | Whether to overlay the snippets with the translation files from `framework.translator.default_path`.     |

Both fields can be overridden per sales channel using the sales channel selector at the top of the config screen — pick a sales channel, set a different value, and save. This uses Shopware's standard system-config inheritance: a sales channel without its own value automatically falls back to the global default. Leaving everything empty is a valid, safe state — the plugin simply stays inactive and does not alter Shopware's default translation behaviour.

## Commands

### Pull snippets

Pulls snippets from the configured provider(s) and writes them locally into the directory defined by `framework.translator.default_path`.

```bash
bin/console sw:snippets:pull
```

The command takes no arguments or options; it resolves everything from the plugin configuration:

* The global default provider (if configured) is pulled for all system locales and written to the `messages` translation domain.
* Every sales channel whose configured provider **differs** from the global default is pulled for that channel's locales and written to a domain named after the sales channel id. Sales channels that merely inherit the default are not pulled again — the `messages` domain already covers them.

If nothing is configured, the command prints a warning and exits without writing anything.

### Push snippets

Pushes local snippets to the configured provider.

```bash
bin/console sw:snippets:push [salesChannelId ...]
```

**Arguments:**

* `salesChannelId` (optional, multiple): the sales channel id(s) to push for. If omitted or set to `default`, the global default provider is used; otherwise the effective provider of each given sales channel is used.

**Options:**

* `--force` / `-f`: overwrite translations that already exist on the provider (removes messages that are not synchronised).
* `--delete-missing`: delete translations on the provider that no longer exist locally.
* `--locales` / `-l` (multiple): restrict to specific locales (e.g. `de-DE`, `en-GB`). If omitted, all relevant locales are pushed. Locales that are not enabled cause the command to fail without touching the provider.

### Flush translation cache

Invalidates the translation cache. Useful after a pull to make new translations visible in the storefront.

```bash
bin/console sw:cache:translation:flush
```

## API endpoints

Both endpoints live under the `/api` scope and require an authenticated admin API token.

### Trigger a translation update

Dispatches an asynchronous translation refresh for the given sales channels. This is intended for webhooks from translation providers (e.g. fired when a translation job completes).

* **URL:** `POST /api/_action/nlx-translation/update`
* **Body (JSON):**
  ```json
  {
    "salesChannelIds": ["SALES_CHANNEL_ID_1", "SALES_CHANNEL_ID_2"]
  }
  ```

Sales channels without a resolvable provider are filtered out. If none remain, the endpoint responds with HTTP 503 and `errorMissingTranslationProvider`.

### List available providers

Returns the translation providers registered in `framework.translator.providers`. This backs the provider select field in the administration.

* **URL:** `GET /api/_action/nlx-translation/providers`

## Asynchronous processing

When the update endpoint is called, the sales channel ids are dispatched to the Shopware message queue in batches. A message handler processes the queue and, per sales channel, invalidates the translation cache and warms up the catalogue for each of the channel's domains — so the refresh happens in the background without blocking the request.

## Development

```bash
composer test        # run the unit test suite (PHPUnit)
composer phpstan     # static analysis
composer lint        # mago lint
composer format:fix  # mago formatter
```
