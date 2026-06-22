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
    public const SHOW_PAYMENT_METHODS_IN_MAIN = 'SIMPAY_SHOW_PAYMENT_METHODS_IN_MAIN';
    public const PAYMENT_METHODS_LIST_IN_MAIN = 'SIMPAY_PAYMENT_METHODS_LIST_IN_MAIN';
    public const SHOW_SEPARATE_PAYMENT_METHODS = 'SIMPAY_SHOW_SEPARATE_PAYMENT_METHODS';
    public const SEPARATE_PAYMENT_METHODS_LIST = 'SIMPAY_SEPARATE_PAYMENT_METHODS_LIST';
    public const SHOW_BLIK_IN_WIDGET = 'SIMPAY_SHOW_BLIK_IN_WIDGET';
    public const BLIK_ONECLICK_ENABLED = 'SIMPAY_BLIK_ONECLICK_ENABLED';
    public const IPN_CHECK_IP = 'SIMPAY_IPN_CHECK_IP';
    public const REPAYMENT_ENABLED = 'SIMPAY_REPAYMENT_ENABLED';

    private ConfigurationInterface $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /** @return array{
     *     api_password: string,
     *     service_id: string,
     *     service_ipn_signature_key: string,
     *     show_payment_methods_in_main: boolean,
     *     payment_methods_list_in_main: string,
     *     show_separate_payment_methods: boolean,
     *     separate_payment_methods_list: string,
     *     show_blik_in_widget: boolean,
     *     ipn_check_ip: boolean,
     *     repayment_enabled: boolean
     *  }
     */
    public function getConfiguration(): array
    {
        return [
            'api_password' => (string)$this->configuration->get(self::API_PASSWORD),
            'service_id' => (string)$this->configuration->get(self::SERVICE_ID),
            'service_ipn_signature_key' => (string)$this->configuration->get(self::SERVICE_IPN_SIGNATURE_KEY),
            'show_payment_methods_in_main' => (bool)$this->configuration->get(self::SHOW_PAYMENT_METHODS_IN_MAIN),
            'payment_methods_list_in_main' => (string)$this->configuration->get(self::PAYMENT_METHODS_LIST_IN_MAIN),
            'show_separate_payment_methods' => (bool)$this->configuration->get(self::SHOW_SEPARATE_PAYMENT_METHODS),
            'separate_payment_methods_list' => (string)$this->configuration->get(self::SEPARATE_PAYMENT_METHODS_LIST),
            'show_blik_in_widget' => (bool)$this->configuration->get(self::SHOW_BLIK_IN_WIDGET),
            'blik_oneclick_enabled' => (bool)$this->configuration->get(self::BLIK_ONECLICK_ENABLED),
            'ipn_check_ip' => (bool)$this->configuration->get(self::IPN_CHECK_IP),
            'repayment_enabled' => (bool)$this->configuration->get(self::REPAYMENT_ENABLED),
        ];
    }

    /**
     * @param array{
     *     api_password: string,
     *     service_id: string,
     *     service_ipn_signature_key: string,
     *     show_payment_methods_in_main: boolean,
     *     payment_methods_list_in_main: string,
     *     show_separate_payment_methods: boolean,
     *     separate_payment_methods_list: string,
     *     show_blik_in_widget: boolean,
     *     ipn_check_ip: boolean,
     *     repayment_enabled: boolean
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
        $this->configuration->set(self::SHOW_PAYMENT_METHODS_IN_MAIN, $configuration['show_payment_methods_in_main']);
        $this->configuration->set(self::PAYMENT_METHODS_LIST_IN_MAIN, $configuration['payment_methods_list_in_main']);
        $this->configuration->set(self::SHOW_SEPARATE_PAYMENT_METHODS, $configuration['show_separate_payment_methods']);
        $this->configuration->set(self::SEPARATE_PAYMENT_METHODS_LIST, $configuration['separate_payment_methods_list']);
        $this->configuration->set(self::SHOW_BLIK_IN_WIDGET, $configuration['show_blik_in_widget']);
        $this->configuration->set(self::BLIK_ONECLICK_ENABLED, $configuration['blik_oneclick_enabled'] ?? false);
        $this->configuration->set(self::IPN_CHECK_IP, $configuration['ipn_check_ip']);
        $this->configuration->set(self::REPAYMENT_ENABLED, $configuration['repayment_enabled']);
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
            && isset($configuration['show_payment_methods_in_main'])
            && isset($configuration['payment_methods_list_in_main'])
            && isset($configuration['show_separate_payment_methods'])
            && isset($configuration['separate_payment_methods_list'])
            && isset($configuration['show_blik_in_widget'])
            && isset($configuration['ipn_check_ip'])
            && isset($configuration['repayment_enabled']);
    }
}
