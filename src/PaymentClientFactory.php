<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop;

use PrestaShop\PrestaShop\Core\ConfigurationInterface;
use SimPay\SDK\SimPay;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;

final class PaymentClientFactory
{
    private readonly ConfigurationInterface $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    public function __invoke(): SimPay
    {
        return new SimPay(
            bearerToken: (string) $this->configuration->get(SimpayDataConfiguration::API_PASSWORD),
            serviceId: (string) $this->configuration->get(SimpayDataConfiguration::SERVICE_ID),
            signatureKey: (string) $this->configuration->get(SimpayDataConfiguration::SERVICE_IPN_SIGNATURE_KEY),
            platform: 'prestashop',
            platformVersion: _PS_VERSION_
        );
    }
}