<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Form;

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

final class SimpayDataConfiguration implements DataConfigurationInterface
{
    public const API_KEY = 'SIMPAY_API_KEY';
    public const API_PASSWORD = 'SIMPAY_API_PASSWORD';
    public const SERVICE_ID = 'SIMPAY_SERVICE_ID';
    public const SERVICE_IPN_SIGNATURE_KEY = 'SIMPAY_SERVICE_IPN_SIGNATURE_KEY';

    private ConfigurationInterface $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /** @return array{api_key: string, api_password: string, service_id: string, service_ipn_signature_key: string} */
    public function getConfiguration(): array
    {
        return [
            'api_key' => (string) $this->configuration->get(self::API_KEY),
            'api_password' => (string) $this->configuration->get(self::API_PASSWORD),
            'service_id' => (string) $this->configuration->get(self::SERVICE_ID),
            'service_ipn_signature_key' => (string) $this->configuration->get(self::SERVICE_IPN_SIGNATURE_KEY),
        ];
    }

    /**
     * @param array{api_key: string, api_password: string, service_id: string, service_ipn_signature_key: string} $configuration
     * @return array<string>
     */
    public function updateConfiguration(array $configuration)
    {
        if (!$this->validateConfiguration($configuration)) {
            return ['Invalid configuration'];
        }

        $this->configuration->set(self::API_KEY, $configuration['api_key']);
        $this->configuration->set(self::API_PASSWORD, $configuration['api_password']);
        $this->configuration->set(self::SERVICE_ID, $configuration['service_id']);
        $this->configuration->set(self::SERVICE_IPN_SIGNATURE_KEY, $configuration['service_ipn_signature_key']);



        return [];
    }

    /**
     * @param array<string, string> $configuration
     */
    public function validateConfiguration(array $configuration): bool
    {
        return isset($configuration['api_key'])
            && isset($configuration['api_password'])
            && isset($configuration['service_id'])
            && isset($configuration['service_ipn_signature_key']);
    }
}
