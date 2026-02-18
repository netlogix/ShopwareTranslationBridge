# Shopware Translation Bridge

This plugin provides a bridge to connect Shopware with any Translation Provider that is supported by the [Symfony Translation Component](https://symfony.com/doc/current/translation.html#translation-providers). It allows you to manage your storefront snippets via a third-party translation service.

## Installation

```bash
composer require netlogix/shopware-translation-bridge
bin/console plugin:install --activate ShopwareTranslationBridge
```

## Configuration

The connection to the translation provider is configured via a DSN (Data Source Name). You need to create a configuration file, for example `config/packages/shopware_translation_bridge.yaml`, to set up the providers.

The plugin uses the DSN from the `ShopwareTranslationBridge.config.providerDsn` system config key as a default. You can also configure a specific DSN for each sales channel.

| option                    | type           | default | info                                                                                               |
|---------------------------|----------------|---------|----------------------------------------------------------------------------------------------------|
| default_provider          | `null\|string` | `null`  | Service name from `framework.translator.providers`. If `null` there is no fallback provider.       |
| respect_translation_files | `bool`         | `true`  | should it overlay the snippet files with the translation files `framework.translator.default_path` |
| sales_channel_providers   | `array`        | `[]`    | SalesChannel specific providers. Like `default_provider` but individiual for every salesChannel    |

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
bin/console sw:cache:flush:translation
```

## API Endpoint

This plugin provides an API endpoint to trigger a translation update for specific sales channels. This is useful for integrating with webhooks from translation providers (e.g., when translations are completed).

*   **URL:** `/api/_action/nlx/translation/update`
*   **Method:** `POST`
*   **Body (JSON):**
    ```json
    {
      "salesChannelIds": ["SALES_CHANNEL_ID_1", "SALES_CHANNEL_ID_2"]
    }
    ```

## Asynchronous Processing

When the API endpoint is called, a message is dispatched to the Shopware message queue for each specified sales channel. A message handler then processes the queue and updates the translations for each sales channel asynchronously in the background.
