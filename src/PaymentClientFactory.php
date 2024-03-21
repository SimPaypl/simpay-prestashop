<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop;

use PrestaShop\PrestaShop\Core\ConfigurationInterface;
use Simpay\Configuration as SimpayConfiguration;
use Simpay\HttpClientFactory;
use Simpay\PaymentApi;
use Simpay\PaymentInterface;

final class PaymentClientFactory
{
    private readonly ConfigurationInterface $configuration;
    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }
    public function __invoke(): PaymentInterface
    {
        return new PaymentApi(
            new HttpClientFactory(
                new SimpayConfiguration(
                    $this->configuration->get('SIMPAY_API_KEY'),
                    $this->configuration->get('SIMPAY_API_PASSWORD'),
                    'en',
                )
            )
        );
    }
}
