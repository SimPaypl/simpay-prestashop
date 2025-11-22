<?php

declare(strict_types=1);

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\SimPayApiService;

final class SimpayValidateModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    public function postProcess()
    {
        $this->assertModuleIsActive();

        if (!$this->checkIfContextIsValid()) {
            $this->redirectToOrder();

            return;
        }

        if (!$this->checkIfPaymentOptionIsAvailable()) {
            $this->redirectToOrder();

            return;
        }

        if (Tools::getToken('simpay') !== Tools::getValue('token')) {
            $this->redirectToOrder();

            return;
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

        /** @var string $serviceIdString */
        $response = $paymentClient->createPayment(
            Configuration::get(SimpayDataConfiguration::SERVICE_ID),
            ['json' => $this->createPaymentRequest($cart, $customer->secure_key, Tools::getValue('method'))],
        );

        if ($response->getStatusCode() !== 201) {
            PrestaShopLogger::addLog(
                'SimPayPayment: Generate error: ' . $response->getContent(false),
                3,
                0,
                'Cart',
                $cart->id,
                true,
            );

            $this->errors[] = 'Nie udało się zainicjować płatności. Spróbuj ponownie lub skontaktuj się ze sklepem';

            Tools::redirect($this->context->link->getPageLink(
                'order',
                true,
                null,
                ['step' => 3],
            ));
            return;
        }

        $json = json_decode($response->getContent(), false);

        $this->module->validateOrder(
            (int)$cart->id,
            (int)Configuration::get(Simpay::CONFIG_OS_AWAITING),
            $cart->getOrderTotal(),
            $this->trans('SimPay', [], 'Modules.Simpay.Shop'),
            null,
            [
                'transaction_id' => $json->data->transactionId,
            ],
            $currency->id,
            false,
            $customer->secure_key,
        );

        $this->setTemplate('module:simpay/views/templates/front/validate.tpl');
        $this->context->smarty?->assign([
            'action' => $json->data->redirectUrl,
        ]);
        return;
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

    private function createPaymentRequest(Cart $cart, string $customerSecureKey, string|bool|null $channel = null): array
    {
        $amount = (float)$cart->getOrderTotal();

        /** @var Link $link */
        $link = $this->context->link;

        $successReturnUrl = $link->getPageLink(
            'order-confirmation',
            true,
            $this->context->language?->id,
            [
                'id_cart' => (int)$cart->id,
                'id_module' => (int)$this->module->id,
                'id_order' => $this->module->currentOrder,
                'key' => $customerSecureKey
            ]
        );

        $failureReturnUrl = $link->getModuleLink(
            'simpay',
            'failed',
            [],
            true,
            $this->context->language?->id,
        );

        if ($channel && !in_array($channel, ['blik', 'blik-paylater', 'paypo'])) {
            $channel = null;
        }

        $payload = [
            'amount' => $amount,
            'currency' => 'PLN',
            'description' => 'Zamówienie ' . (string)$cart->id,
            'control' => (string)$cart->id,
            'customer' => array_filter([
                'name' => mb_substr($this->context->customer->firstname . ' ' . $this->context->customer->lastname, 0, 64),
                'email' => mb_substr($this->context->customer->email, 0, 64),
                'ip' => Tools::getRemoteAddr(),
                'countryCode' => 'PL',
            ]),
            'antifraud' => [
                'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'systemId' => $this->context->customer->is_guest ? null : $this->context->customer->id,
            ],
            'returns' => [
                'success' => $successReturnUrl,
                'failure' => $failureReturnUrl,
            ],
        ];

        if ($channel) {
            $payload['directChannel'] = $channel;
        }

        $billingId = $cart->id_address_invoice;
        $shippingId = $cart->id_address_delivery;
        if ($billingId && $address = new Address($billingId)) {
            $country = new Country($address->id_country);
            $payload['billing'] = array_filter([
                'name' => mb_substr($address->firstname, 0, 64),
                'surname' => mb_substr($address->lastname, 0, 64),
                'street' => mb_substr($address->address1, 0, 64),
                'building' => mb_substr($address->address2, 0, 16),
                'city' => mb_substr($address->city, 0, 64),
                'postalCode' => mb_substr($address->postcode, 0, 64),
                'country' => $country->iso_code,
                'company' => mb_substr($address->company, 0, 64),
            ]);
        }
        if ($shippingId && $address = new Address($billingId)) {
            $country = new Country($address->id_country);
            $payload['shipping'] = array_filter([
                'name' => mb_substr($address->firstname, 0, 64),
                'surname' => mb_substr($address->lastname, 0, 64),
                'street' => mb_substr($address->address1, 0, 64),
                'building' => mb_substr($address->address2, 0, 16),
                'city' => mb_substr($address->city, 0, 64),
                'postalCode' => mb_substr($address->postcode, 0, 64),
                'country' => $country->iso_code,
                'company' => mb_substr($address->company, 0, 64),
            ]);
        }

        return $payload;
    }
}
