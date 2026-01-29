<?php


declare(strict_types=1);

namespace SimPaypl\PrestaShop\Service;

use Address;
use Cart;
use Country;
use Link;
use PrestaShopBundle\Translation\TranslatorComponent;
use Tools;
use Context;
use PrestaShop\PrestaShop\Adapter\LegacyContext;

final class SimPayPaymentRequestBuilder
{
    private Context $context;

    private TranslatorComponent $translator;

    public function __construct(LegacyContext $legacyContext)
    {
        $this->context = $legacyContext->getContext();
        $this->translator = $this->context->getTranslator();
    }

    /**
     * Build SimPay create payment payload.
     *
     * @param Cart $cart
     * @param string $customerSecureKey
     * @param string|null $channel SimPay channel id (optional)
     * @param int|null $orderId Existing order id (retry) or newly created order id (validate)
     *
     * @return array<string, mixed>
     */
    public function build(Cart $cart, string $customerSecureKey, ?string $channel = null, ?int $orderId = null): array
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
                'id_module' => (int)($this->context->controller?->module?->id ?? 0),
                'id_order' => (int)($orderId ?? 0),
                'key' => $customerSecureKey,
            ]
        );

        $failureReturnUrl = $link->getModuleLink(
            'simpay',
            'failed',
            [],
            true,
            $this->context->language?->id
        );

        $payload = [
            'amount' => $amount,
            'currency' => 'PLN',
            'description' => $this->translator->trans('Order', [], 'Modules.Simpay.Shop') . ' ' . (string)$cart->id,
            'control' => (string)$cart->id,

            'customer' => array_filter([
                'name' => mb_substr(trim(($this->context->customer->firstname ?? '') . ' ' . ($this->context->customer->lastname ?? '')), 0, 64),
                'email' => mb_substr((string)($this->context->customer->email ?? ''), 0, 64),
                'ip' => Tools::getRemoteAddr(),
                'countryCode' => 'PL',
            ]),

            'antifraud' => [
                'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'systemId' => ($this->context->customer && !$this->context->customer->is_guest) ? (string)$this->context->customer->id : null,
            ],

            'returns' => [
                'success' => $successReturnUrl,
                'failure' => $failureReturnUrl,
            ],
        ];

        if (!empty($channel)) {
            $payload['directChannel'] = $channel;
        }

        // Add billing/shipping if available
        $this->appendAddresses($payload, $cart);

        return $payload;
    }

    /**
     * Append billing and shipping data to payload (if available).
     *
     * @param array<string, mixed> $payload
     * @param Cart $cart
     *
     * @return void
     */
    private function appendAddresses(array &$payload, Cart $cart): void
    {
        $billingId = (int)$cart->id_address_invoice;
        $shippingId = (int)$cart->id_address_delivery;

        if ($billingId > 0) {
            $billingAddress = new Address($billingId);
            if ((int)$billingAddress->id > 0) {
                $payload['billing'] = $this->formatAddress($billingAddress);
            }
        }

        if ($shippingId > 0) {
            $shippingAddress = new Address($shippingId);
            if ((int)$shippingAddress->id > 0) {
                $payload['shipping'] = $this->formatAddress($shippingAddress);
            }
        }
    }

    /**
     * Format Address to SimPay structure.
     *
     * @param Address $address
     *
     * @return array<string, mixed>
     */
    private function formatAddress(Address $address): array
    {
        $country = new Country((int)$address->id_country);

        return array_filter([
            'name' => mb_substr((string)$address->firstname, 0, 64),
            'surname' => mb_substr((string)$address->lastname, 0, 64),
            'street' => mb_substr((string)$address->address1, 0, 64),
            'building' => mb_substr((string)$address->address2, 0, 16),
            'city' => mb_substr((string)$address->city, 0, 64),
            'postalCode' => mb_substr((string)$address->postcode, 0, 64),
            'country' => (string)$country->iso_code,
            'company' => mb_substr((string)$address->company, 0, 64),
        ]);
    }
}
