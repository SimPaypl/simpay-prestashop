<?php

declare(strict_types=1);

use Simpay\Model\Request\Amount;
use Simpay\Model\Request\CallbackReturnUrl;
use Simpay\Model\Request\Control;
use Simpay\Model\Request\CreatePayment;
use Simpay\Model\Request\ServiceId;
use Simpay\Model\Request\Currency as SimpayCurrency;
use Simpay\PaymentInterface;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;

final class SimpayValidateModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;
    public function postProcess(): void
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

        /** @var Cart $cart */
        $cart = $this->context->cart;
        /** @var Currency $currency */
        $currency = $this->context->currency;

        $customer = new Customer($cart->id_customer);
        if (false === Validate::isLoadedObject($customer)) {
            $this->redirectToOrder();

            return;
        }

        /** @var PaymentInterface $paymentClient */
        $paymentClient = $this->get('prestashop.module.simpay.front.payment_client');

        /** @var string $serviceIdString */
        $serviceIdString = Configuration::get(SimpayDataConfiguration::SERVICE_ID);
        $response = $paymentClient->paymentTransactionCreate(
            new ServiceId($serviceIdString),
            $this->createPaymentRequest($cart, $customer->secure_key),
        );

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get(Simpay::CONFIG_OS_AWAITING),
            $cart->getOrderTotal(),
            $this->trans('SimPay', [], 'Modules.Simpay.Shop'),
            null,
            [
                'transaction_id' => $response->transactionId,
            ],
            $currency->id,
            false,
            $customer->secure_key,
        );

        $this->setTemplate('module:simpay/views/templates/front/validate.tpl');
        $this->context->smarty?->assign([
            'action' => $response->redirectUrl,
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
            (int) $this->context->language?->id,
            [
                'step' => 1,
            ]
        ));
    }

    private function createPaymentRequest(Cart $cart, string $customerSecureKey): CreatePayment
    {
        $amount = (float) $cart->getOrderTotal();

        /** @var Link $link */
        $link = $this->context->link;

        $successReturnUrl = $link->getPageLink(
            'order-confirmation',
            true,
            $this->context->language?->id,
            [
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $this->module->id,
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

        return new CreatePayment(
            new Amount($amount),
            [],
            null,
            new SimpayCurrency('PLN'),
            null,
            new Control((string) $cart->id),
            null,
            null,
            null,
            new CallbackReturnUrl($successReturnUrl, $failureReturnUrl),
        );
    }
}
