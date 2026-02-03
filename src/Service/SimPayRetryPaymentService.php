<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Service;

use Configuration;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use Context;
use Customer;
use Db;
use Order;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use Validate;

final class SimPayRetryPaymentService
{
    private Context $context;

    public function __construct(
        private readonly \Simpay $module,
        private ?LegacyContext $legacyContext = null
    ) {
        $this->context = $legacyContext?->getContext() ?? Context::getContext();
    }

    public function hookDisplayOrderDetail(array $params, string $moduleFile): string
    {
        if (!(bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)) {
            return '';
        }

        $order = $params['order'] ?? null;
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return '';
        }

        if (!\Simpay::isUpdatableState((int) $order->current_state)) {
            return '';
        }

        if ($order->module !== $this->module->name) {
            return '';
        }

        if (!isset($this->context->link, $this->context->smarty)) {
            return '';
        }

        $retryUrl = $this->getRetryUrl($order);
        if ($retryUrl === '') {
            return '';
        }

        $this->context->smarty->assign([
            'simpay_retry_url' => $retryUrl,
            'payment_name' => $order->payment,
        ]);

        return $this->module->display($moduleFile, 'views/templates/hook/repayment.tpl');
    }

    public function buildRetryToken(Order $order): string
    {
        return hash('sha256', $order->reference . '|' . $order->secure_key . '|' . (int) $order->id);
    }

    public function isValidRetryToken(Order $order, string $token): bool
    {
        return hash_equals($this->buildRetryToken($order), (string) $token);
    }

    public function getRetryUrl(Order $order): string
    {
        if (
            !(bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)
            || !isset($this->context->link)
        ) {
            return '';
        }

        return $this->context->link->getModuleLink(
            $this->module->name,
            'retry',
            [
                'id_order' => (int) $order->id,
                'token' => $this->buildRetryToken($order),
            ],
            true
        );
    }
    public function hookActionGetExtraMailTemplateVars(array &$params): void
    {
        $template = (string) ($params['template'] ?? '');

        switch ($template) {
            case 'order_conf':
                $this->enrichOrderConfirmationTemplate($params);
                break;

            case 'simpay_retry_payment':
                $this->enrichRetryPaymentTemplate($params);
                break;
        }
    }

    private function enrichOrderConfirmationTemplate(array &$params): void
    {
        if (empty($params['template_vars']['{payment}'])) {
            return;
        }

        if (!(bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)) {
            return;
        }

        $paymentLabel = (string) $params['template_vars']['{payment}'];
        if (stripos($paymentLabel, $this->module->name) !== 0) {
            return;
        }

        $order = $this->resolveOrderFromParams($params);
        if (!$order) {
            return;
        }

        $customer = new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($customer) || !isset($this->context->link)) {
            return;
        }

        $url = $this->getRetryUrl($order);
        if ($url === '') {
            return;
        }

        $params['extra_template_vars']['{payment}'] =
            $paymentLabel
            . ', <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . $this->context->getTranslator()->trans('Retry payment', [], 'Modules.Simpay.Admin')
            . '</a>';
    }

    private function enrichRetryPaymentTemplate(array &$params): void
    {
        if (!(bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)) {
            return;
        }

        $order = $this->resolveOrderFromParams($params);
        if (!$order || $order->module !== $this->module->name) {
            return;
        }

        $retryUrl = $this->getRetryUrl($order);
        if ($retryUrl === '') {
            return;
        }

        $params['extra_template_vars']['{retry_url}'] = $retryUrl;
        $params['extra_template_vars']['{retry_button_label}'] = $this->context
            ->getTranslator()
            ->trans('Retry payment', [], 'Modules.Simpay.Shop');
    }

    private function resolveOrderFromParams(array $params): ?Order
    {
        $orderId = (int) ($params['id_order'] ?? 0);

        if (!$orderId && !empty($params['template_vars']['{id_order}'])) {
            $orderId = (int) $params['template_vars']['{id_order}'];
        }

        if (!$orderId && !empty($params['template_vars']['{order_name}'])) {
            $reference = pSQL((string) $params['template_vars']['{order_name}']);
            $orderId = (int) Db::getInstance()->getValue(
                'SELECT id_order FROM ' . _DB_PREFIX_ . 'orders WHERE reference = \'' . $reference . '\''
            );
        }

        if ($orderId <= 0) {
            return null;
        }

        $order = new Order($orderId);

        return Validate::isLoadedObject($order) ? $order : null;
    }
}
