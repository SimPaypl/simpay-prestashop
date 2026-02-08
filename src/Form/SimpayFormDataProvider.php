<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Form;

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;
use SimPaypl\PrestaShop\Update\UpdateChecker;

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
     * show_payment_methods_in_main: boolean,
     * payment_methods_list_in_main: string,
     * show_blik_separately: boolean,
     * show_blik_in_widget: boolean,
     * show_blik_bnpl_separately: boolean,
     * show_paypo_separately: boolean,
     * repayment_enabled: boolean,
     * update_info: ?array
     * }
     */
    public function getData(): array
    {
        $data = $this->simpayDataConfiguration->getConfiguration();

        // Update info (optional)
        $data['update_info'] = null;
        try {
            $container = SymfonyContainer::getInstance();
            if ($container) {
                /** @var UpdateChecker $updateChecker */
                $updateChecker = $container->get('prestashop.module.simpay.update_checker');
                $update = $updateChecker->getUpdateIfAvailable();
                if ($update) {
                    $data['update_info'] = $update;
                }
            }
        } catch (\Throwable $e) {
            $data['update_info'] = null;
        }

        return $data;
    }

    /**
     * @param array{
     *      api_password: string,
     *      service_id: string,
     *      service_ipn_signature_key: string,
     *      show_payment_methods_in_main: boolean,
     *      payment_methods_list_in_main: string,
     *      show_blik_separately: boolean,
     *      show_blik_in_widget: boolean,
     *      show_blik_bnpl_separately: boolean,
     *      show_paypo_separately: boolean,
     *      repayment_enabled: boolean
     *   } $data
     * @return array<string>
     */
    public function setData(array $data): array
    {
        return $this->simpayDataConfiguration->updateConfiguration($data);
    }
}
