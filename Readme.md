# Shopware Translation Bridge

This plugin provides a bridge to connect Shopware with any Translation Provider that is supported by the [Symfony Translation Component](https://symfony.com/doc/current/translation.html#translation-providers). It allows you to manage your storefront snippets via a third-party translation service.

## Installation

```bash
composer require netlogix/shopware-translation-bridge
bin/console plugin:install --activate ShopwareTranslationBridge
```

## Configuration

The Symfony Translation providers themselves (the DSNs) are still configured the usual Symfony way, e.g. in `config/packages/translation.yaml` under `framework.translator.providers`.

Everything specific to this plugin is configured as regular **Shopware plugin settings** - no extra config file needed. Open Administration > Extensions > My extensions > Translation Bridge > Config:

| field                          | type     | default | info                                                                                                    |
|---------------------------------|----------|---------|----------------------------------------------------------------------------------------------------------|
| Default translation provider   | `string` | empty   | Service name from `framework.translator.providers` (e.g. `tolgee`). Empty means no fallback provider.    |
| Respect local translation files | `bool`   | `true`  | Whether to overlay the snippet files with the translation files from `framework.translator.default_path` |

Both fields can be overridden per sales channel using the sales channel selector at the top of that config screen - pick a sales channel, set a different "Default translation provider" (or "Respect local translation files"), and save. Leaving a sales channel's field empty falls back to the global default.

## Commands

This plugin provides three commands to manage translations.

### Push Snippets

Pushes all local snippets to the configured translation provider.

```bash
bin/console sw:snippets:push [salesChannelId1] [salesChannelId2]
```

**Arguments:**

*   `salesChannelId` (optional, multiple): The sales channel ID(s) to push translations for. If "default" or empty, the default provider is used.

**Options:**

*   `--force` / `-f`: Overwrite existing translations on the provider.
*   `--delete-missing`: Delete translations on the provider that do not exist locally.
*   `--locales` / `-l` (multiple): Specify the locales to push (e.g., `en-GB`, `de-DE`). If not provided, all relevant locales are pushed.

### Pull Snippets

Pulls all snippets from the configured translation provider and saves them locally inside the translation directory defined by `framework.translator.default_path`. The default provider (if configured) is written to the `messages` translation domain, while every sales channel with an explicit provider override is persisted to a domain that matches the sales channel id.

```bash
bin/console sw:snippets:pull [salesChannelId1]
```

**Arguments:**

*   `salesChannelId` (optional, multiple): The sales channel ID(s) to pull translations for. If "default" or empty, the default provider is used.

**Options:**

*   `--locales` / `-l` (multiple): Specify the locales to pull. If not provided, all relevant locales are pulled.

### Flush Translation Cache

Flushes the translation cache. This is useful after pulling new translations to make them visible in the storefront.

```bash
bin/console sw:cache:translation:flush
```

## API Endpoint

This plugin provides an API endpoint to trigger a translation update for specific sales channels. This is useful for integrating with webhooks from translation providers (e.g., when translations are completed).

*   **URL:** `/api/_action/nlx-translation/update`
*   **Body (JSON):**
    ```json
    {
      "salesChannelIds": ["SALES_CHANNEL_ID_1", "SALES_CHANNEL_ID_2"]
    }
    ```

## Asynchronous Processing

When the API endpoint is called, a message is dispatched to the Shopware message queue for each specified sales channel. A message handler then processes the queue and updates the translations for each sales channel asynchronously in the background.
