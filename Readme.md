# Symfony Translation Bridge

With this plugin you can use the provider which can be defined in symfony/trnaslation to manage the storefront snippets.

## Installation

```bash
composer req netlogix/shopware-translation-bridge
```

## Configuration

| option                    | type           | default | info                                                                                               |
|---------------------------|----------------|---------|----------------------------------------------------------------------------------------------------|
| default_provider          | `null\|string` | `null`  | Service name from `framework.translator.providers`. If `null` there is no fallback provider.       |
| respect_translation_files | `bool`         | `true`  | should it overlay the snippet files with the translation files `framework.translator.default_path` |        
| sales_channel_providers   | `array`        | `[]`    | SalesChannel spesific providers. Like `default_provider` but individial for every salesChannel     |

### Example config

```yaml
nlx_storefront_translation:
  default_provider: 'providerServiceName'
  respect_translation_files: true
  sales_channel_providers:
    2b919afec10730f413cb5682bbed09fd:
      provider: 'providerServiceName'
    sales_channel_providers:
      2b919afec10730f413cb5682bbed09fd:
        provider: 'fooPprovider'
    e1582cd277454e988b8de2b878effc94:
      provider: 'barProvider'
```

## Commands

### Push snippets to translation provider

To initial push the snippets to the translation provider you can use this command.
It provides several options to tailor the push to your needs.

```bash
bin/console sw:snippets:push
```

### Flush translation cache

With this command you can clear only the translation cache

```bash
bin/console cache:translation:flush
```

### Fetch translations from providers

Use this command to pull the remote storefront translations and store them locally inside the translation directory defined by `framework.translator.default_path`. The default provider (if configured) is written to the `messages` translation domain, while every entry of `sales_channel_providers` is persisted to a domain that matches the configured sales channel id.

```bash
bin/console sw:snippets:pull
```

## Components

### Netlogix\ShopwareTranslationBridge\Core\System\RelevantLocaleResolverInterface