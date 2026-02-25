<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Support\Provider;

use JsonException;
use RuntimeException;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\TranslatorBag;

final class InMemoryTestProviderFactory
{
    public function createProvider(string $name, array $config): InMemoryTestProvider
    {
        return new InMemoryTestProvider($name, $this->createTranslatorBag($config));
    }

    public function createCollectionFromJsonFile(string $jsonFile): TranslationProviderCollection
    {
        $config = $this->decodeJsonFile($jsonFile);
        $providers = is_array($config['providers'] ?? null) ? $config['providers'] : [];

        $collection = [];

        foreach ($providers as $name => $providerConfig) {
            $collection[(string) $name] = $this->createProvider((string) $name, (array) $providerConfig);
        }

        return new TranslationProviderCollection($collection);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFile(string $jsonFile): array
    {
        $rawJson = file_get_contents($jsonFile);
        if ($rawJson === false) {
            throw new RuntimeException(sprintf('Could not read JSON fixture "%s".', $jsonFile));
        }

        try {
            $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('JSON fixture "%s" contains invalid JSON: %s', $jsonFile, $exception->getMessage()),
                0,
                $exception
            );
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createTranslatorBag(array $config): TranslatorBag
    {
        $cataloguesConfig = is_array($config['catalogues'] ?? null) ? $config['catalogues'] : [];
        $bag = new TranslatorBag();

        foreach ($cataloguesConfig as $locale => $domainsConfig) {
            $catalogue = new MessageCatalogue((string) $locale);
            foreach ((array) $domainsConfig as $domain => $messages) {
                $catalogue->add($this->normalizeMessages((array) $messages), (string) $domain);
            }

            $bag->addCatalogue($catalogue);
        }

        return $bag;
    }

    /**
     * @param array<int|string, mixed> $messages
     * @return array<string, string>
     */
    private function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $key => $value) {
            $normalized[(string) $key] = (string) $value;
        }

        return $normalized;
    }
}
