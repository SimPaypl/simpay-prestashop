<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Form;

use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

final class SimpayFormDataProvider implements FormDataProviderInterface
{
    private SimpayDataConfiguration $simpayDataConfiguration;

    public function __construct(SimpayDataConfiguration $simpayDataConfiguration)
    {
        $this->simpayDataConfiguration = $simpayDataConfiguration;
    }

    /** @return array{
     * api_password: string,
     * service_id: string,
     * service_ipn_signature_key: string,
     * show_blik_separately: boolean,
     * show_blik_bnpl_separately: boolean,
     * show_paypo_separately: boolean
     * }
     */
    public function getData(): array
    {
        return $this->simpayDataConfiguration->getConfiguration();

    }

    /**
     * @param array{
     *      api_password: string,
     *      service_id: string,
     *      service_ipn_signature_key: string,
     *      show_blik_separately: boolean,
     *      show_blik_bnpl_separately: boolean,
     *      show_paypo_separately: boolean
     *   } $data
     * @return array<string>
     */
    public function setData(array $data): array
    {
        return $this->simpayDataConfiguration->updateConfiguration($data);
    }
}
