<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Service;

use Configuration;
use Db;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPayLogger;

final class SimPayBlikAliasService
{
    /**
     * Check if OneClick is enabled in module configuration.
     */
    public function isOneClickEnabled(): bool
    {
        return (bool) Configuration::get(SimpayDataConfiguration::BLIK_ONECLICK_ENABLED);
    }

    /**
     * Get the alias label - always uses the shop name.
     */
    public function getAliasLabel(): string
    {
        return (string) Configuration::get('PS_SHOP_NAME');
    }

    /**
     * Get alias value for a customer (their unique ID in our system).
     */
    public function getAliasValue(int $customerId): string
    {
        return 'customer_' . $customerId;
    }

    /**
     * Find active alias for a customer.
     *
     * @return array{id_simpay_blik_alias: int, alias_uuid: string, alias_value: string, status: string}|null
     */
    public function findActiveAlias(int $customerId): ?array
    {
        $sql = sprintf(
            "SELECT * FROM `%ssimpay_blik_alias` WHERE `id_customer` = %d AND `status` = 'alias_active' ORDER BY `updated_at` DESC",
            _DB_PREFIX_,
            $customerId
        );

        $row = Db::getInstance()->getRow($sql);

        return is_array($row) && !empty($row) ? $row : null;
    }

    /**
     * Find pending alias for a customer (registration sent, waiting for IPN).
     */
    public function findPendingAlias(int $customerId): ?array
    {
        $sql = sprintf(
            "SELECT * FROM `%ssimpay_blik_alias` WHERE `id_customer` = %d AND `status` = 'pending' ORDER BY `created_at` DESC",
            _DB_PREFIX_,
            $customerId
        );

        $row = Db::getInstance()->getRow($sql);

        return is_array($row) && !empty($row) ? $row : null;
    }

    /**
     * Register a new alias attempt for a customer (before IPN confirms it).
     */
    public function registerAlias(int $customerId, string $aliasValue, string $label): void
    {
        $now = date('Y-m-d H:i:s');

        // Check if alias already exists for this value
        $existing = Db::getInstance()->getRow(sprintf(
            "SELECT * FROM `%ssimpay_blik_alias` WHERE `alias_value` = '%s'",
            _DB_PREFIX_,
            pSQL($aliasValue)
        ));

        if ($existing) {
            // Update existing
            Db::getInstance()->update('simpay_blik_alias', [
                'status' => 'pending',
                'alias_label' => pSQL($label),
                'updated_at' => $now,
            ], sprintf("`alias_value` = '%s'", pSQL($aliasValue)));
        } else {
            Db::getInstance()->insert('simpay_blik_alias', [
                'id_customer' => $customerId,
                'alias_value' => pSQL($aliasValue),
                'alias_label' => pSQL($label),
                'alias_uuid' => null,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Activate alias after receiving IPN confirmation.
     * Called when blik:alias_status_changed with status alias_active is received.
     */
    public function activateAlias(string $aliasValue, string $aliasUuid, string $status): bool
    {
        $now = date('Y-m-d H:i:s');

        $existing = Db::getInstance()->getRow(sprintf(
            "SELECT * FROM `%ssimpay_blik_alias` WHERE `alias_value` = '%s'",
            _DB_PREFIX_,
            pSQL($aliasValue)
        ));

        if (!$existing) {
            SimPayLogger::warning('BLIK alias activation: alias not found in DB', [
                'alias_value' => $aliasValue,
                'alias_uuid' => $aliasUuid,
            ]);
            return false;
        }

        return (bool) Db::getInstance()->update('simpay_blik_alias', [
            'alias_uuid' => pSQL($aliasUuid),
            'status' => pSQL($status),
            'updated_at' => $now,
        ], sprintf("`alias_value` = '%s'", pSQL($aliasValue)));
    }

    /**
     * Update alias status (e.g. deactivated, expired).
     */
    public function updateAliasStatus(string $aliasUuid, string $status): bool
    {
        $now = date('Y-m-d H:i:s');

        return (bool) Db::getInstance()->update('simpay_blik_alias', [
            'status' => pSQL($status),
            'updated_at' => $now,
        ], sprintf("`alias_uuid` = '%s'", pSQL($aliasUuid)));
    }

    /**
     * Remove alias for customer (e.g. customer requests removal).
     */
    public function removeAlias(int $customerId): bool
    {
        return (bool) Db::getInstance()->delete(
            'simpay_blik_alias',
            sprintf('`id_customer` = %d', $customerId)
        );
    }

    /**
     * Check if customer has an active alias (can pay without code).
     */
    public function canPayWithoutCode(int $customerId): bool
    {
        if (!$this->isOneClickEnabled()) {
            return false;
        }

        return $this->findActiveAlias($customerId) !== null;
    }

    /**
     * Build alias payload for first payment (registration).
     */
    public function buildRegistrationAliasPayload(int $customerId): array
    {
        $value = $this->getAliasValue($customerId);
        $label = $this->getAliasLabel();

        return [
            'value' => $value,
            'type' => 'UID',
            'label' => $label,
        ];
    }

    /**
     * Build alias payload for OneClick payment (using uuid - Method A).
     */
    public function buildOneClickAliasPayload(array $activeAlias): array
    {
        $payload = [
            'uuid' => $activeAlias['alias_uuid'],
            'label' => $this->getAliasLabel(),
        ];

        return $payload;
    }

}
