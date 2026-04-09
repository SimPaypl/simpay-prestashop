<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Service;

use Address;
use Cart;
use Context;
use Country;
use Currency;
use Customer;
use Db;
use Link;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShopBundle\Translation\TranslatorComponent;
use Tools;

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
        $amount = (float) $cart->getOrderTotal();

        /** @var Link $link */
        $link = $this->context->link;

        $successReturnUrl = $link->getPageLink(
            'order-confirmation',
            true,
            $this->context->language?->id,
            [
                'id_cart' => (int) $cart->id,
                'id_module' => (int) ($this->context->controller?->module?->id ?? 0),
                'id_order' => (int) ($orderId ?? 0),
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
            'description' => $this->translator->trans('Order', [], 'Modules.Simpay.Shop') . ' ' . (string) $cart->id,
            'control' => (string) $cart->id,

            'customer' => $this->filterNullable([
                'name' => mb_substr(trim(($this->context->customer->firstname ?? '') . ' ' . ($this->context->customer->lastname ?? '')), 0, 64),
                'email' => mb_substr((string) ($this->context->customer->email ?? ''), 0, 64),
                'ip' => Tools::getRemoteAddr(),
                'countryCode' => 'PL',
            ]),

            'antifraud' => $this->filterNullable([
                'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'systemId' => ($this->isLoggedCustomer()) ? (string) $this->context->customer->id : null,
            ]),

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

        // Add additional customer context only for logged customers
        $this->appendCustomerContext($payload, $cart);
        
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
        $billingId = (int) $cart->id_address_invoice;
        $shippingId = (int) $cart->id_address_delivery;

        if ($billingId > 0) {
            $billingAddress = new Address($billingId);
            if ((int) $billingAddress->id > 0) {
                $payload['billing'] = $this->formatAddress($billingAddress);
            }
        }

        if ($shippingId > 0) {
            $shippingAddress = new Address($shippingId);
            if ((int) $shippingAddress->id > 0) {
                $payload['shipping'] = $this->formatAddress($shippingAddress);
            }
        }
    }

    /**
     * Append additional customer context for logged customers only.
     *
     * @param array<string, mixed> $payload
     * @param Cart $cart
     *
     * @return void
     */
    private function appendCustomerContext(array &$payload, Cart $cart): void
    {
        if (!$this->isLoggedCustomer()) {
            return;
        }

        $customer = $this->context->customer;
        $currencyIso = $this->getCartCurrencyIso($cart);

        $salesStats = $this->getCustomerSalesStats(
            (int) $customer->id,
            (int) $cart->id_currency
        );

        $context = $this->filterNullable([
            'accountCreatedAt' => $this->formatDate((string) $customer->date_add),
            'salesTotalCount' => $salesStats['salesTotalCount'],
            'salesTotalAmount' => $salesStats['salesTotalAmount'],
            'salesAvgAmount' => $salesStats['salesAvgAmount'],
            'salesMaxAmount' => $salesStats['salesMaxAmount'],
            'accountSetCurrency' => $currencyIso,
            'hasPreviousPurchases' => $salesStats['salesTotalCount'] > 0,
        ]);

        if ($context !== []) {
            $payload['context'] = $context;
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
        $country = new Country((int) $address->id_country);

        return $this->filterNullable([
            'name' => mb_substr((string) $address->firstname, 0, 64),
            'surname' => mb_substr((string) $address->lastname, 0, 64),
            'street' => mb_substr((string) $address->address1, 0, 64),
            'building' => mb_substr((string) $address->address2, 0, 16),
            'city' => mb_substr((string) $address->city, 0, 64),
            'postalCode' => mb_substr((string) $address->postcode, 0, 64),
            'country' => (string) $country->iso_code,
            'company' => mb_substr((string) $address->company, 0, 64),
        ]);
    }

    /**
     * Check whether current context contains a logged customer.
     *
     * @return bool
     */
    private function isLoggedCustomer(): bool
    {
        return $this->context->customer instanceof Customer
            && (int) $this->context->customer->id > 0
            && !(bool) $this->context->customer->is_guest;
    }

    /**
     * Get cart currency ISO code.
     *
     * @param Cart $cart
     *
     * @return string|null
     */
    private function getCartCurrencyIso(Cart $cart): ?string
    {
        $currencyId = (int) $cart->id_currency;
        if ($currencyId <= 0) {
            return null;
        }

        $currency = new Currency($currencyId);

        if ((int) $currency->id <= 0 || empty($currency->iso_code)) {
            return null;
        }

        return mb_substr((string) $currency->iso_code, 0, 3);
    }

    /**
     * Get aggregated customer sales stats from valid orders in current cart currency.
     *
     * @param int $customerId
     * @param int $currencyId
     *
     * @return array<string, int|float>
     */
    private function getCustomerSalesStats(int $customerId, int $currencyId): array
    {
        if ($customerId <= 0) {
            return [
                'salesTotalCount' => 0,
                'salesTotalAmount' => 0.0,
                'salesAvgAmount' => 0.0,
                'salesMaxAmount' => 0.0,
            ];
        }

        $sql = '
            SELECT
                COUNT(o.id_order) AS salesTotalCount,
                COALESCE(SUM(o.total_paid_tax_incl), 0) AS salesTotalAmount,
                COALESCE(AVG(o.total_paid_tax_incl), 0) AS salesAvgAmount,
                COALESCE(MAX(o.total_paid_tax_incl), 0) AS salesMaxAmount
            FROM `' . _DB_PREFIX_ . 'orders` o
            WHERE o.id_customer = ' . (int) $customerId . '
              AND o.valid = 1
              AND o.id_currency = ' . (int) $currencyId;

        /** @var array<string, string|int|float>|false $row */
        $row = Db::getInstance()->getRow($sql);

        if (!is_array($row)) {
            return [
                'salesTotalCount' => 0,
                'salesTotalAmount' => 0.0,
                'salesAvgAmount' => 0.0,
                'salesMaxAmount' => 0.0,
            ];
        }

        return [
            'salesTotalCount' => (int) ($row['salesTotalCount'] ?? 0),
            'salesTotalAmount' => round((float) ($row['salesTotalAmount'] ?? 0), 2),
            'salesAvgAmount' => round((float) ($row['salesAvgAmount'] ?? 0), 2),
            'salesMaxAmount' => round((float) ($row['salesMaxAmount'] ?? 0), 2),
        ];
    }

    /**
     * Convert PrestaShop datetime string to ISO 8601 format.
     *
     * @param string $value
     *
     * @return string|null
     */
    private function formatDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Remove only null and empty-string values.
     *
     * This preserves false, 0 and 0.0 values.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function filterNullable(array $data): array
    {
        return array_filter(
            $data,
            static fn ($value): bool => $value !== null && $value !== ''
        );
    }
}