<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Form;

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

final class SimpayDataConfiguration implements DataConfigurationInterface
{
    public const API_PASSWORD = 'SIMPAY_API_PASSWORD';
    public const SERVICE_ID = 'SIMPAY_SERVICE_ID';
    public const SERVICE_IPN_SIGNATURE_KEY = 'SIMPAY_SERVICE_IPN_SIGNATURE_KEY';
    public const SHOW_BLIK_SEPARATELY = 'SIMPAY_SHOW_BLIK_SEPARATELY';
    public const SHOW_BLIK_BNPL_SEPARATELY = 'SIMPAY_SHOW_BLIK_BNPL_SEPARATELY';
    public const SHOW_PAYPO_SEPARATELY = 'SIMPAY_SHOW_PAYPO_SEPARATELY';
    public const IPN_CHECK_IP = 'SIMPAY_IPN_CHECK_IP';

    private ConfigurationInterface $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /** @return array{
     *     api_password: string,
     *     service_id: string,
     *     service_ipn_signature_key: string,
     *     show_blik_separately: boolean,
     *     show_blik_bnpl_separately: boolean,
     *     show_paypo_separately: boolean,
     *     ipn_check_ip: boolean,
     *  }
     */
    public function getConfiguration(): array
    {
        return [
            'api_password' => (string)$this->configuration->get(self::API_PASSWORD),
            'service_id' => (string)$this->configuration->get(self::SERVICE_ID),
            'service_ipn_signature_key' => (string)$this->configuration->get(self::SERVICE_IPN_SIGNATURE_KEY),
            'show_blik_separately' => (bool)$this->configuration->get(self::SHOW_BLIK_SEPARATELY),
            'show_blik_bnpl_separately' => (bool)$this->configuration->get(self::SHOW_BLIK_BNPL_SEPARATELY),
            'show_paypo_separately' => (bool)$this->configuration->get(self::SHOW_PAYPO_SEPARATELY),
            'ipn_check_ip' => (bool)$this->configuration->get(self::IPN_CHECK_IP),
        ];
    }

    /**
     * @param array{
     *     api_password: string,
     *     service_id: string,
     *     service_ipn_signature_key: string,
     *     show_blik_separately: boolean,
     *     show_blik_bnpl_separately: boolean,
     *     show_paypo_separately: boolean,
     *     ipn_check_ip: boolean,
     * } $configuration
     * @return array<string>
     */
    public function updateConfiguration(array $configuration)
    {
        if (!$this->validateConfiguration($configuration)) {
            return ['Invalid configuration'];
        }

        $this->configuration->set(self::API_PASSWORD, $configuration['api_password']);
        $this->configuration->set(self::SERVICE_ID, $configuration['service_id']);
        $this->configuration->set(self::SERVICE_IPN_SIGNATURE_KEY, $configuration['service_ipn_signature_key']);
        $this->configuration->set(self::SHOW_BLIK_SEPARATELY, $configuration['show_blik_separately']);
        $this->configuration->set(self::SHOW_BLIK_BNPL_SEPARATELY, $configuration['show_blik_bnpl_separately']);
        $this->configuration->set(self::SHOW_PAYPO_SEPARATELY, $configuration['show_paypo_separately']);
        $this->configuration->set(self::IPN_CHECK_IP, $configuration['ipn_check_ip']);

        return [];
    }

    /**
     * @param array<string, string> $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return isset($configuration['api_password'])
            && isset($configuration['service_id'])
            && isset($configuration['service_ipn_signature_key'])
            && isset($configuration['show_blik_separately'])
            && isset($configuration['show_blik_bnpl_separately'])
            && isset($configuration['show_paypo_separately'])
            && isset($configuration['ipn_check_ip']);
    }
}
