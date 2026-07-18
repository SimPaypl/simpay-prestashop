<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Helper;

use Configuration;
use SimPay\SDK\SimPay;

/**
 * Caches SimPay channels list in PrestaShop Configuration.
 *
 * Strategy:
 * - If cache is fresh (TTL) -> return it.
 * - If cache is stale -> try to refresh from API.
 * - If refresh fails -> return stale cache (if exists) to keep checkout stable.
 */
final class SimPayChannelCache
{
    private const CONFIG_KEY = 'SIMPAY_CHANNELS_CACHE';
    private const TTL_SECONDS = 86400; // 24h

    // Blocklist of methods by ID
    private const BLOCKED_IDS = [
         'blik-level0',
         'blik-recurrent'
    ];

    private SimPay $simpay;

    public function __construct(SimPay $simpay)
    {
        $this->simpay = $simpay;
    }

    /**
     * Get channels with cache.
     *
     * @param bool $forceRefresh
     * @return array<int, array{id:string,name:string,type:string,img:?string}>
     */
    public function get(bool $forceRefresh = false): array
    {
        $cache = $this->readCache();

        if (!$forceRefresh && $this->isFresh($cache)) {
            // Auto-refresh if cache is missing 'commission' field (schema migration)
            if (!empty($cache['channels']) && !array_key_exists('commission', $cache['channels'][0] ?? [])) {
                $forceRefresh = true;
            } else {
                return $cache['channels'];
            }
        }

        try {
            $channels = $this->fetchFromApi();

            $this->writeCache([
                'updated_at' => time(),
                'channels' => $channels,
            ]);

            return $channels;
        } catch (\Throwable $e) {
            // API failed: return stale cache if available, otherwise empty array
            if (isset($cache['channels']) && is_array($cache['channels'])) {
                return $cache['channels'];
            }

            return [];
        }
    }

    /**
     * Force refresh channels from API and save to cache.
     *
     * @param string $serviceId
     * @return array<int, array{id:string,name:string,type:string,img:?string}>
     */
    public function refresh(string $serviceId): array
    {
        return $this->get(true);
    }

    /**
     * Clear cache completely.
     */
    public function clear(): void
    {
        Configuration::deleteByName(self::CONFIG_KEY);
    }

    /**
     * Get raw cache payload (for debug / admin view).
     */
    public function getPayload(): array
    {
        return $this->readCache();
    }

    private function readCache(): array
    {
        $raw = (string) Configuration::get(self::CONFIG_KEY);

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeCache(array $payload): void
    {
        Configuration::updateValue(
            self::CONFIG_KEY,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function isFresh(array $cache): bool
    {
        if (
            !isset($cache['updated_at'], $cache['channels']) ||
            !is_array($cache['channels'])
        ) {
            return false;
        }


        $age = time() - (int) $cache['updated_at'];

        return $age >= 0 && $age < self::TTL_SECONDS;
    }

    /**
     * Fetch channels from API and normalize response.
     *
     * IMPORTANT:
     * SimPay SDK getChannels() should return the "data" list (array of channels).
     *
     * @return array<int, array{id:string,name:string,type:string,img:?string}>
     */
    private function fetchFromApi(): array
    {
        $rawChannels = $this->simpay->client()->getChannels();


        if (!is_array($rawChannels)) {
            return [];
        }

        $out = [];

        foreach ($rawChannels as $c) {
            if (!is_array($c)) {
                continue;
            }

            $id = $c['id'] ?? null;
            $name = $c['name'] ?? null;
            $type = $c['type'] ?? null;
            $amounts = $c['amount'] ?? null;

            if (!$id || !$name || !$type || !$amounts) {
                continue;
            }

            if (in_array($id, self::BLOCKED_IDS, true)) {
                continue;
            }

            $out[] = [
                'id' => (string) $id,
                'name' => (string) $name,
                'type' => (string) $type,
                'img' => isset($c['img']) ? (string) $c['img'] : null,
                'amounts' => (array) $amounts,
                'commission' => isset($c['commission']) ? (float) $c['commission'] : 0.0,
            ];
        }

        return $out;
    }
}