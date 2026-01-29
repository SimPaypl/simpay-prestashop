<?php

declare(strict_types=1);

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use SimPaypl\PrestaShop\Service\SimPayRetryPaymentService;
use SimPaypl\PrestaShop\SimPayApiService;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;

final class SimpayValidateModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    private bool $isRetryFlow = false;
    private ?Order $retryOrder = null;

    public function postProcess()
    {
        $this->assertModuleIsActive();
        $this->prepareRetryContext();

        if (!$this->checkIfContextIsValid()) {
            $this->redirectToOrder();
            return;
        }

        if (!$this->checkIfPaymentOptionIsAvailable()) {
            $this->redirectToOrder();
            return;
        }

        $providedToken = (string) Tools::getValue('token');
        if (!$this->isRetryFlow) {
            $expectedToken = Tools::getToken('simpay');

            if ($expectedToken !== $providedToken) {
                PrestaShopLogger::addLog('[SimPay] Invalid payment token', 3);
                $this->redirectToOrder();
                return;
            }
        }

        /** @var Cart $cart */
        $cart = $this->context->cart;
        /** @var Currency $currency */
        $currency = $this->context->currency;

        $customer = new Customer($cart->id_customer);
        if (false === Validate::isLoadedObject($customer)) {
            $this->redirectToOrder();
            return;
        }

        /** @var SimPayApiService $paymentClient */
        $paymentClient = $this->get('prestashop.module.simpay.front.payment_client');

        $method = Tools::getValue('method');
        if (!$method && Tools::getValue('simpay_method_choice')) {
            $method = Tools::getValue('simpay_method_choice');
        }

        /** @var \SimPaypl\PrestaShop\Helper\SimPayChannelCache $channelCache */
        $channelCache = $this->get('prestashop.module.simpay.channel_cache');
        $channels = $channelCache->get();
        $allowedChannelIds = array_column($channels, 'id');

        if ($method && !in_array($method, $allowedChannelIds, true)) {
            $method = null;
        }

        /** @var \SimPaypl\PrestaShop\Service\PaymentRequestBuilder $builder */
        $builder = $this->get('prestashop.module.simpay.payment_request_builder');
        $builderOrderId = $this->retryOrder ? (int) $this->retryOrder->id : (int) $this->module->currentOrder;
        $payload = $builder->build($cart, $customer->secure_key, $method ?: null, $builderOrderId);

        /** @var string $serviceIdString */
        $response = $paymentClient->createPayment(['json' => $payload]);

        if ($response->getStatusCode() !== 201) {
            PrestaShopLogger::addLog(
                'SimPayPayment: Generate error: ' . $response->getContent(false),
                3,
                0,
                'Cart',
                $cart->id,
                true,
            );

            $this->errors[] = $this->trans(
                'We could not initialize the payment. Please try again or contact the shop.',
                [],
                'Modules.Simpay.Shop'
            );

            Tools::redirect($this->context->link->getPageLink(
                'order',
                true,
                null,
                ['step' => 3],
            ));
            return;
        }

        $json = json_decode($response->getContent(), false);

        $cartId = (int) $cart->id;
        $orderStateAwaiting = (int) Configuration::get(Simpay::CONFIG_OS_AWAITING);
        $orderTotal = $cart->getOrderTotal();
        $paymentName = $this->trans('SimPay', [], 'Modules.Simpay.Shop');
        $paymentDetails = [
            'transaction_id' => $json->data->transactionId,
        ];
        $currencyId = $currency->id;
        $secureKey = $customer->secure_key;

        if (!$this->isRetryFlow) {
            $this->module->validateOrder(
                $cartId,
                $orderStateAwaiting,
                $orderTotal,
                $paymentName,
                null,
                $paymentDetails,
                $currencyId,
                false,
                $secureKey,
            );
        } else {
            $this->refreshOrderStateAfterRetry();
        }

        $this->setTemplate('module:simpay/views/templates/front/validate.tpl');
        $this->context->smarty?->assign([
            'action' => $json->data->redirectUrl,
        ]);
    }

    private function assertModuleIsActive(): void
    {
        if (!Module::isEnabled($this->module->name)) {
            die($this->trans('This payment method is not available.', [], 'Modules.Simpay.Shop'));
        }

        if (!$this->module->active) {
            die($this->trans('SimPay module module isn\'t active.', [], 'Modules.Simpay.Shop'));
        }
    }

    private function checkIfContextIsValid(): bool
    {
        if (null === $this->context->cart) {
            return false;
        }

        if (null === $this->context->currency) {
            return false;
        }

        return Validate::isLoadedObject($this->context->cart)
            && Validate::isUnsignedInt($this->context->cart->id_customer)
            && Validate::isUnsignedInt($this->context->cart->id_address_delivery)
            && Validate::isUnsignedInt($this->context->cart->id_address_invoice);
    }

    private function checkIfPaymentOptionIsAvailable(): bool
    {
        $modules = Module::getPaymentModules();

        if (empty($modules)) {
            return false;
        }

        foreach ($modules as $module) {
            if (isset($module['name']) && $this->module->name === $module['name']) {
                return true;
            }
        }

        return false;
    }

    private function redirectToOrder(): void
    {
        /** @var Link $link */
        $link = $this->context->link;

        Tools::redirect($link->getPageLink(
            'order',
            true,
            (int)$this->context->language?->id,
            [
                'step' => 1,
            ]
        ));
    }

    private function prepareRetryContext(): void
    {
        $retryOrderId = (int) Tools::getValue('retry_order_id');
        if ($retryOrderId <= 0) {
            return;
        }

        if (!(bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)) {
            return;
        }

        $retryToken = (string) Tools::getValue('retry_token');
        /** @var SimPayRetryPaymentService $retryService */
        $retryService = $this->get('prestashop.module.simpay.retry_payment_service');

        $order = new Order($retryOrderId);
        if (
            !Validate::isLoadedObject($order)
            || $order->module !== $this->module->name
            || !$retryService->isValidRetryToken($order, $retryToken)
            || !\Simpay::isUpdatableState((int) $order->current_state)
        ) {
            $this->redirectToOrder();
        }

        $cart = new Cart((int) $order->id_cart);
        $customer = new Customer((int) $order->id_customer);
        if (!Validate::isLoadedObject($cart) || !Validate::isLoadedObject($customer)) {
            $this->redirectToOrder();
        }

        $formToken = (string) Tools::getValue('retry_form_token');
        $sessionToken = $this->getStoredRetryFormToken();

        if ($formToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $formToken)) {
            $this->clearRetryFormToken();
            $this->redirectToOrder();
        }

        $this->clearRetryFormToken();

        $this->context->cart = $cart;
        $this->context->customer = $customer;
        $this->context->currency = new Currency((int) $cart->id_currency);
        $this->context->cookie->id_cart = (int) $cart->id;
        $this->context->cookie->id_currency = (int) $cart->id_currency;
        $this->context->cookie->id_customer = (int) $customer->id;
        $this->context->cookie->id_address_delivery = (int) $cart->id_address_delivery;
        $this->context->cookie->id_address_invoice = (int) $cart->id_address_invoice;

        $this->module->currentOrder = (int) $order->id;
        $this->isRetryFlow = true;
        $this->retryOrder = $order;
    }

    private function refreshOrderStateAfterRetry(): void
    {
        if (
            !$this->retryOrder
            || !(bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)
        ) {
            return;
        }

        $awaitingState = (int) Configuration::get(Simpay::CONFIG_OS_AWAITING);
        if ($awaitingState <= 0 || (int) $this->retryOrder->current_state === $awaitingState) {
            return;
        }

        $history = new OrderHistory();
        $history->id_order = (int) $this->retryOrder->id;
        $history->changeIdOrderState($awaitingState, (int) $this->retryOrder->id, false);
        $history->save();
        $this->retryOrder->current_state = $awaitingState;
    }

    private function clearRetryFormToken(): void
    {
        if (!isset($this->context->cookie)) {
            return;
        }

        unset($this->context->cookie->simpay_retry_form_token);
        $this->context->cookie->write();
    }

    private function getStoredRetryFormToken(): string
    {
        if (!isset($this->context->cookie->simpay_retry_form_token)) {
            return '';
        }

        return (string) $this->context->cookie->simpay_retry_form_token;
    }
}
