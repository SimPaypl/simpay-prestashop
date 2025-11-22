<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop;

use PrestaShop\PrestaShop\Core\ConfigurationInterface;

final class PaymentClientFactory
{
    private readonly ConfigurationInterface $configuration;
    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }
    public function __invoke(): SimPayApiService
    {
        return new SimPayApiService($this->configuration->get('SIMPAY_API_PASSWORD'));
    }
}
