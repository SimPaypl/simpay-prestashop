<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Update;

use Configuration;
use Tools;

final class UpdateChecker
{
    private const ENDPOINT = 'https://api.simpay.pl/ecommerce/plugin/prestashop/version/';
    private const TTL_SECONDS = 3600;

    private const CONFIG_LATEST_VERSION = 'SIMPAY_UPDATE_LATEST_VERSION';
    private const CONFIG_ZIP_URL = 'SIMPAY_UPDATE_ZIP_URL';
    private const CONFIG_CHECKED_AT = 'SIMPAY_UPDATE_CHECKED_AT';

    private ?string $moduleVersion = null;

    public function __construct(?\Module $module = null)
    {
        $this->moduleVersion = $module?->version;
    }

    /**
     * Returns update data if a newer version is available, otherwise null.
     * Array format: ['latest_version' => string, 'zip_url' => string, 'checked_at' => int]
     */
    public function getUpdateIfAvailable(): ?array
    {
        if ($this->moduleVersion === '') {
            return null;
        }

        $cache = $this->readCache();

        if ($this->isFresh($cache)) {
            return $this->buildResultIfNewer($cache, $this->moduleVersion);
        }

        $latest = $this->fetchLatest();
        if (!$latest) {
            return null;
        }

        $payload = [
            'latest_version' => $latest['latest_version'],
            'zip_url' => $latest['zip_url'],
            'checked_at' => time(),
        ];

        $this->writeCache($payload);

        return $this->buildResultIfNewer($payload, $this->moduleVersion);
    }

    private function buildResultIfNewer(array $cache, string $currentVersion): ?array
    {
        $latest = (string) ($cache['latest_version'] ?? '');
        $zipUrl = (string) ($cache['zip_url'] ?? '');

        if ($latest === '' || $zipUrl === '') {
            return null;
        }

        if (version_compare($latest, $currentVersion, '<=')) {
            return null;
        }

        return [
            'latest_version' => $latest,
            'zip_url' => $zipUrl,
            'checked_at' => (int) ($cache['checked_at'] ?? 0),
        ];
    }

    /**
     * Fetches latest version data from API and validates URL.
     */
    private function fetchLatest(): ?array
    {
        try {
            $response = $this->httpGet(self::ENDPOINT, 3);
            $json = json_decode($response, true);

            if (!is_array($json) || empty($json['success']) || empty($json['data'])) {
                return null;
            }

            $data = (array) $json['data'];
            $latestVersion = (string) ($data['version'] ?? '');
            $zipUrl = (string) ($data['zip_url'] ?? '');

            if ($latestVersion === '' || !$this->isValidUrl($zipUrl)) {
                return null;
            }

            if (!$this->isUrlReachable($zipUrl, 3)) {
                return null;
            }

            return [
                'latest_version' => $latestVersion,
                'zip_url' => $zipUrl,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function httpGet(string $url, int $timeout): string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $raw = Tools::file_get_contents($url, false, $context);

        return is_string($raw) ? $raw : '';
    }

    private function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Lightweight URL check (HEAD/GET) with short timeout.
     */
    private function isUrlReachable(string $url, int $timeout): bool
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $code >= 200 && $code < 400;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'HEAD',
            ],
        ]);

        $headers = @get_headers($url, true, $context);

        if (!is_array($headers) || empty($headers[0])) {
            return false;
        }

        return preg_match('/\s(2\d\d|3\d\d)\s/', (string) $headers[0]) === 1;
    }

    private function readCache(): array
    {
        return [
            'latest_version' => (string) Configuration::get(self::CONFIG_LATEST_VERSION),
            'zip_url' => (string) Configuration::get(self::CONFIG_ZIP_URL),
            'checked_at' => (int) Configuration::get(self::CONFIG_CHECKED_AT),
        ];
    }

    private function writeCache(array $payload): void
    {
        Configuration::updateValue(self::CONFIG_LATEST_VERSION, (string) ($payload['latest_version'] ?? ''));
        Configuration::updateValue(self::CONFIG_ZIP_URL, (string) ($payload['zip_url'] ?? ''));
        Configuration::updateValue(self::CONFIG_CHECKED_AT, (int) ($payload['checked_at'] ?? time()));
    }

    private function isFresh(array $cache): bool
    {
        $checkedAt = (int) ($cache['checked_at'] ?? 0);
        if ($checkedAt <= 0) {
            return false;
        }

        $age = time() - $checkedAt;

        return $age >= 0 && $age < self::TTL_SECONDS;
    }
}
