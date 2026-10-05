# Shopware Translation Bridge

This plugin provides a bridge to connect Shopware with any Translation Provider that is supported by the [Symfony Translation Component](https://symfony.com/doc/current/translation.html#translation-providers). It allows you to manage your storefront snippets via a third-party translation service.

## Requirements

* Shopware `~6.7.1`
* PHP `>=8.4`
* A Symfony translation provider bridge, e.g. `symfony/crowdin-translation-provider`, `symfony/lokalise-translation-provider`, `symfony/loco-translation-provider` or `symfony/phrase-translation-provider`
* A running message queue worker (for the asynchronous translation update)

## Installation

```bash
composer require netlogix/shopware-translation-bridge
bin/console plugin:install --activate ShopwareTranslationBridge
```

## Configuration

The connection to the translation provider is configured via a DSN (Data Source Name) in the Symfony translator configuration (`framework.translator.providers`). The plugin then references these providers by their name. You can configure a default provider and a specific provider for each sales channel.

```yaml
# config/packages/translation.yaml
framework:
  translator:
    providers:
      providerServiceName:
        dsn: '%env(CROWDIN_DSN)%'
        domains: ['messages']
        locales: ['de-DE', 'en-GB']
```

The plugin itself is configured in `config/packages/shopware_translation_bridge.yaml`:

| option                    | type           | default | info                                                                                               |
|---------------------------|----------------|---------|----------------------------------------------------------------------------------------------------|
| default_provider          | `null\|string` | `null`  | Service name from `framework.translator.providers`. If `null` there is no fallback provider.       |
| respect_translation_files | `bool`         | `true`  | should it overlay the snippet files with the translation files `framework.translator.default_path` |
| sales_channel_providers   | `array`        | `[]`    | SalesChannel specific providers. Like `default_provider` but individual for every salesChannel    |

### Example Configuration

Here is an example of how to configure different providers for different sales channels.

```yaml
# config/packages/shopware_translation_bridge.yaml
shopware_translation_bridge:
  # Define a default provider for all sales channels
  default_provider: 'providerServiceName'
  respect_translation_files: true
  sales_channel_providers:
    # Assign a specific provider for a sales channel by its ID
    2b919afec10730f413cb5682bbed09fd:
      provider: 'providerServiceName'
```

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

Pulls all snippets from the configured translation provider and saves them locally inside the translation directory defined by `framework.translator.default_path`. The default provider (if configured) is written to the `messages` translation domain, while every entry of `sales_channel_providers` is persisted to a domain that matches the configured sales channel id.

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
bin/console cache:translation:flush
```

## API Endpoint

This plugin provides an Admin API endpoint to trigger a translation update. This is useful for integrating with webhooks from translation providers (e.g., when translations are completed). The same action is available in the administration under *Settings > System > Caches & indexes* ("Update translations").

*   **URL:** `/api/_action/nlx-translation/update`
*   **Method:** `POST`
*   **Body:** none
*   **Required privilege:** `system:cache:info`

All sales channels that have a translation provider (either a sales channel specific one or the default provider) are updated. If no provider is configured at all, the endpoint answers with `503 Service Unavailable`.

## Asynchronous Processing

When the API endpoint is called, messages (in batches of 5 sales channels) are dispatched to the Shopware message queue. A message handler then processes the queue and updates the translations for each sales channel asynchronously in the background.
