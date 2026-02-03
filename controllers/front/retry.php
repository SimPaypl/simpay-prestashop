<?php

declare(strict_types=1);

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Service\SimPayRetryPaymentService;

final class SimpayRetryModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    public function postProcess()
    {
        $idOrder = (int)Tools::getValue('id_order');
        $token   = (string)Tools::getValue('token');

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            Tools::redirect('index.php');
        }

        if (!(bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)) {
            Tools::redirect($this->getOrderViewUrl($order));
        }

        /** @var SimPayRetryPaymentService $retryService */
        $retryService = $this->get('prestashop.module.simpay.retry_payment_service');
        // Validate retry token (guest-safe)
        if (!$retryService->isValidRetryToken($order, $token)) {
            Tools::redirect('index.php');
        }

        $cart = new Cart((int)$order->id_cart);
        if (!Validate::isLoadedObject($cart)) {
            Tools::redirect($this->getOrderViewUrl($order));
        }

        $customer = new Customer((int)$order->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect($this->getOrderViewUrl($order));
        }

        $this->setTemplate('module:simpay/views/templates/front/retry.tpl');

        if (!$this->module->isUpdatableState((int) $order->current_state)) {
            $this->context->smarty->assign([
                'canRetry' => false,
                'orderReference' => $order->reference,
                'orderViewUrl' => $this->getOrderViewUrl($order),
            ]);
            return;
        }

        $this->hydrateContextForRetry($cart, $customer);

        $retryFormToken = $this->generateRetryFormToken();
        $this->storeRetryFormToken($retryFormToken);

        $this->context->smarty->assign([
            'canRetry' => true,
            'orderReference' => $order->reference,
            'retryValidateUrl' => $this->context->link->getModuleLink($this->module->name, 'validate', [], true),
            'token' => Tools::getToken('simpay'),
            'retryOrderId' => (int) $order->id,
            'retryToken' => $token,
            'retryFormToken' => $retryFormToken,
        ]);
    }

    private function hydrateContextForRetry(Cart $cart, Customer $customer): void
    {
        $this->context->cart = $cart;
        $this->context->customer = $customer;
        $this->context->currency = new Currency((int) $cart->id_currency);
        $this->context->language = new Language((int) ($cart->id_lang ?: $this->context->language?->id));

        $this->context->cookie->id_cart = (int) $cart->id;
        $this->context->cookie->id_currency = (int) $cart->id_currency;
        $this->context->cookie->id_customer = (int) $customer->id;
        $this->context->cookie->id_address_delivery = (int) $cart->id_address_delivery;
        $this->context->cookie->id_address_invoice = (int) $cart->id_address_invoice;
    }

    private function generateRetryFormToken(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            return Tools::passwdGen(64);
        }
    }

    private function storeRetryFormToken(string $token): void
    {
        if (!isset($this->context->cookie)) {
            return;
        }

        $this->context->cookie->simpay_retry_form_token = $token;
        $this->context->cookie->write();
    }

    private function getOrderViewUrl(Order $order): string
    {
        $customer = new Customer((int)$order->id_customer);

        if (Validate::isLoadedObject($customer) && (bool)$customer->is_guest) {
            // Guest: guest-tracking
            $urlParams = [
                'email' => (string)$customer->email,
                'order_reference' => (string)$order->reference,
            ];

            return $this->context->link->getPageLink('guest-tracking', true, (int)$this->context->language?->id, $urlParams);
        }

        // Logged: order detail
        $urlParams = ['id_order' => (int)$order->id];

        return $this->context->link->getPageLink('order-detail', true, (int)$this->context->language?->id, $urlParams);
    }
}
