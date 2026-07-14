<?php

declare(strict_types=1);

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPayLogger;
use SimPaypl\PrestaShop\Service\SimPayBlikAliasService;
use SimPaypl\PrestaShop\Service\SimPayPaymentAttemptService;
use SimPaypl\PrestaShop\Service\SimPayPaymentRequestBuilder;
use SimPay\SDK\SimPay as SimPaySDK;

final class SimpayBlikModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    public function postProcess()
    {
        $this->ajax = true;

        $action = (string) Tools::getValue('action', 'init');
        if ($action === 'status') {
            return $this->handleStatus();
        }
        if ($action === 'oneclick') {
            return $this->handleOneClick();
        }
        if ($action === 'check_oneclick') {
            return $this->handleCheckOneClick();
        }

        if (!Module::isEnabled($this->module->name) || !$this->module->active) {
            return $this->respondError($this->trans('Payment method is not available.', [], 'Modules.Simpay.Shop'));
        }

        if (!(bool) Configuration::get(SimpayDataConfiguration::SHOW_BLIK_IN_WIDGET)) {
            return $this->respondError($this->trans('BLIK widget is disabled.', [], 'Modules.Simpay.Shop'));
        }

        $token = (string) Tools::getValue('token');
        if ($token === '' || $token !== Tools::getToken('simpay')) {
            return $this->respondError($this->trans('Invalid payment token.', [], 'Modules.Simpay.Shop'));
        }

        $cartId = (int) Tools::getValue('cart_id');
        $cart = $cartId > 0 ? new Cart($cartId) : $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            return $this->respondError($this->trans('Cart not found.', [], 'Modules.Simpay.Shop'));
        }

        $currency = new Currency((int) $cart->id_currency);
        if ($currency->iso_code !== 'PLN') {
            return $this->respondError($this->trans('BLIK supports PLN only.', [], 'Modules.Simpay.Shop'));
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            return $this->respondError($this->trans('Customer not found.', [], 'Modules.Simpay.Shop'));
        }

        $blikCode = trim((string) Tools::getValue('blik_code'));
        if (!preg_match('/^[0-9]{6}$/', $blikCode)) {
            return $this->respondError($this->trans('Invalid BLIK code format.', [], 'Modules.Simpay.Shop'), 'INVALID_BLIK_CODE_FORMAT');
        }

        $orderId = (int) Order::getIdByCartId((int) $cart->id);
        if ($orderId <= 0) {
            $awaitingState = (int) Configuration::get(Simpay::CONFIG_OS_AWAITING);
            $orderTotal = $cart->getOrderTotal();
            $paymentName = $this->trans('SimPay', [], 'Modules.Simpay.Shop');

            $this->module->validateOrder(
                (int) $cart->id,
                $awaitingState,
                $orderTotal,
                $paymentName,
                null,
                null,
                (int) $currency->id,
                false,
                (string) $customer->secure_key
            );

            $orderId = (int) $this->module->currentOrder;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return $this->respondError($this->trans('Order not found.', [], 'Modules.Simpay.Shop'));
        }

        SimPayLogger::setDefaultOrderId((int) $order->id);

        /** @var SimPayPaymentAttemptService $attemptService */
        $attemptService = $this->module->getService(SimPayPaymentAttemptService::class);

        /** @var SimPayPaymentRequestBuilder $builder */
        $builder = $this->module->getService(SimPayPaymentRequestBuilder::class);
        $payload = $builder->build($cart, (string) $customer->secure_key, 'blik-level0', (int) $order->id);
        /** @var SimPaySDK $simpay */
        $simpay = $this->module->getService(SimPaySDK::class);

        try {
            $json = $simpay->client()->createTransaction($payload);
        } catch (\Throwable $e) {
            SimPayLogger::error($this->trans('BLIK payment initialization failed.', [], 'Modules.Simpay.Logs'), [
                'exception' => $e->getMessage(),
                'class' => get_class($e),
            ]);
            return $this->respondError(
                $this->trans('We could not initialize the BLIK payment.', [], 'Modules.Simpay.Shop'),
                'INIT_FAILED'
            );
        }

        $transactionId = (string) ($json['data']['transactionId'] ?? '');

        if ($transactionId === '') {
            return $this->respondError($this->trans('Missing transaction ID.', [], 'Modules.Simpay.Shop'));
        }

        $attemptService->registerAttempt($order, $transactionId, 'blik-level0', 'checkout');

        // OneClick alias is REQUIRED by SimPay API for every BLIK Level 0 call when OneClick is enabled on service.
        // When paying WITH a code, alias must always be in "register" format (value + type), never uuid.
        // The uuid format is only valid for OneClick (without code) via sendBlikOneClick.
        /** @var SimPayBlikAliasService $aliasService */
        $aliasService = $this->module->getService(SimPayBlikAliasService::class);
        $alias = null;

        if ($aliasService->isOneClickEnabled()
            && (int) $customer->id > 0
            && !(bool) $customer->is_guest
        ) {
            // Always use register format (value + type) for sendBlikLevel0 (with code)
            $alias = $aliasService->buildRegistrationAliasPayload((int) $customer->id);
            $alias['mode'] = 'register';

            // Save to DB only if no existing record (active or pending)
            if (!$aliasService->findActiveAlias((int) $customer->id)
                && !$aliasService->findPendingAlias((int) $customer->id)
            ) {
                $aliasService->registerAlias(
                    (int) $customer->id,
                    $alias['value'],
                    $alias['label']
                );
            }
        }

        $blikResult = $this->sendBlikLevel0($simpay, $transactionId, $blikCode, $alias);

        if (!$blikResult['success']) {
            $attemptService->updateStatus($transactionId, 'blik_code_failed', false);

            SimPayLogger::error($this->trans('BLIK code rejected.', [], 'Modules.Simpay.Logs'), [
                'error_code' => $blikResult['error_code'] ?? null,
            ]);

            return $this->respondError(
                $blikResult['message'] ?? $this->trans('BLIK code rejected.', [], 'Modules.Simpay.Shop'),
                (string) ($blikResult['error_code'] ?? 'BLIK_GENERAL_ERROR')
            );
        }

        $attemptService->updateStatus($transactionId, 'blik_code_accepted', false);

        SimPayLogger::info($this->trans('BLIK code accepted.', [], 'Modules.Simpay.Logs'), [
            'transaction_id' => $transactionId,
        ]);

        return $this->respondOk([
            'transaction_id' => $transactionId,
            'order_id' => (int) $order->id,
            'redirect_url' => '',
            'confirm_required' => true,
        ], $this->trans('Confirm the payment in your banking app.', [], 'Modules.Simpay.Shop'));
    }

    private function sendBlikLevel0(SimPaySDK $simpay, string $transactionId, string $blikCode, ?array $alias = null): array
    {
        $serviceId = (string) Configuration::get(SimpayDataConfiguration::SERVICE_ID);
        if ($serviceId === '') {
            return ['success' => false, 'message' => $this->trans('Missing service ID.', [], 'Modules.Simpay.Shop')];
        }

        $blikAlias = null;
        if ($alias !== null) {
            $blikAlias = \SimPay\SDK\BlikAlias::register(
                $alias['label'],
                $alias['value'],
                $alias['type'] ?? 'UID'
            );
        }

        try {
            $simpay->client()->sendBlikLevel0($transactionId, $blikCode, $blikAlias);
            return ['success' => true];
        } catch (\SimPay\SDK\Exception\ApiException $e) {
            $errorCode = $e->getApiCode() ?? "";
            $message = $e->getMessage();

            SimPayLogger::error('BLIK Level 0 API error', [
                'http_status' => $e->getHttpStatusCode(),
                'api_code' => $errorCode,
                'api_message' => $e->getApiMessage(),
                'full_message' => $message,
                'transaction_id' => $transactionId,
                'has_alias' => $blikAlias !== null,
            ]);

            if ($errorCode !== '') {
                $message = $this->translateBlikErrorCode($errorCode, $message);
            }

            return [
                'success' => false,
                'message' => $message,
                'error_code' => $errorCode,
                'http_status' => $e->getHttpStatusCode(),
            ];
        } catch (\Throwable $e) {
            SimPayLogger::error('BLIK Level 0 unexpected error', [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return ['success' => false, 'message' => $this->trans('BLIK request failed.', [], 'Modules.Simpay.Shop')];
        }
    }

    private function translateBlikErrorCode(string $code, string $fallback = ''): string
    {
        $map = [
            'INVALID_BLIK_CODE' => $this->trans('Invalid BLIK code.', [], 'Modules.Simpay.Shop'),
            'PAYER_APP_NOT_ACTIVE' => $this->trans('BLIK is not active in the payer’s banking app.', [], 'Modules.Simpay.Shop'),
            'PAYER_APP_NOT_FOUND' => $this->trans('BLIK not found in the payer’s banking app.', [], 'Modules.Simpay.Shop'),
            'INVALID_BLIK_CODE_FORMAT' => $this->trans('Invalid BLIK code format.', [], 'Modules.Simpay.Shop'),
            'BLIK_CODE_EXPIRED' => $this->trans('BLIK code expired.', [], 'Modules.Simpay.Shop'),
            'BLIK_CODE_LIMIT' => $this->trans('BLIK code limit exceeded.', [], 'Modules.Simpay.Shop'),
            'BLIK_CODE_CANCELLED' => $this->trans('BLIK code was cancelled.', [], 'Modules.Simpay.Shop'),
            'BLIK_CODE_NOT_SUPPORTED' => $this->trans('BLIK code is not supported.', [], 'Modules.Simpay.Shop'),
            'BLIK_CODE_USED' => $this->trans('BLIK code has already been used.', [], 'Modules.Simpay.Shop'),
            'BLIK_GENERAL_ERROR' => $this->trans('General BLIK error.', [], 'Modules.Simpay.Shop'),
            'BLIK_TECHNICAL_BREAK' => $this->trans('BLIK is temporarily unavailable due to a technical break.', [], 'Modules.Simpay.Shop'),
            'INSUFFICIENT_FUNDS' => $this->trans('Payment failed. Check the reason in the banking app and try again.', [], 'Modules.Simpay.Shop'),
            'LIMIT_EXCEEDED' => $this->trans('Payment failed. Check the reason in the banking app and try again.', [], 'Modules.Simpay.Shop'),
            'TIMEOUT' => $this->trans('Payment failed - not confirmed on time in the banking app. Try again.', [], 'Modules.Simpay.Shop'),
            'GENERAL_ERROR' => $this->trans('Payment failed. Check the reason in the banking app and try again.', [], 'Modules.Simpay.Shop'),
            'SYSTEM_ERROR' => $this->trans('Payment failed. Check the reason in the banking app and try again.', [], 'Modules.Simpay.Shop'),
            'SEC_DECLINED' => $this->trans('Payment failed. Check the reason in the banking app and try again.', [], 'Modules.Simpay.Shop'),
            'USER_DECLINED' => $this->trans('Payment rejected in a banking app. Try again.', [], 'Modules.Simpay.Shop'),
            'TAS_DECLINED' => $this->trans('Payment failed. Check the reason in the banking app and try again.', [], 'Modules.Simpay.Shop'),
            'ALIAS_DECLINED' => $this->trans('Payment without code was declined. Please enter a BLIK code.', [], 'Modules.Simpay.Shop'),
            'USER_TIMEOUT' => $this->trans('Payment failed - not confirmed on time in the banking app. Try again.', [], 'Modules.Simpay.Shop'),
            'ISSUER_DECLINED' => $this->trans('Payment declined by the bank. Try again or use a different payment method.', [], 'Modules.Simpay.Shop'),
        ];

        return $map[$code] ?? ($fallback !== '' ? $fallback : $this->trans('Invalid BLIK code.', [], 'Modules.Simpay.Shop'));
    }

    /**
     * Check if customer can use OneClick (has active alias).
     */
    private function handleCheckOneClick(): void
    {
        $token = (string) Tools::getValue('token');
        if ($token === '' || $token !== Tools::getToken('simpay')) {
            $this->respondError($this->trans('Invalid payment token.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $customer = $this->context->customer;
        if (!$customer || (int) $customer->id <= 0 || (bool) $customer->is_guest) {
            $this->respondOk(['oneclick_available' => false]);
            return;
        }

        /** @var SimPayBlikAliasService $aliasService */
        $aliasService = $this->module->getService(SimPayBlikAliasService::class);

        $this->respondOk([
            'oneclick_available' => $aliasService->canPayWithoutCode((int) $customer->id),
        ]);
    }

    /**
     * Handle OneClick payment (no BLIK code required).
     */
    private function handleOneClick(): void
    {
        if (!Module::isEnabled($this->module->name) || !$this->module->active) {
            $this->respondError($this->trans('Payment method is not available.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $token = (string) Tools::getValue('token');
        if ($token === '' || $token !== Tools::getToken('simpay')) {
            $this->respondError($this->trans('Invalid payment token.', [], 'Modules.Simpay.Shop'));
            return;
        }

        /** @var SimPayBlikAliasService $aliasService */
        $aliasService = $this->module->getService(SimPayBlikAliasService::class);

        if (!$aliasService->isOneClickEnabled()) {
            $this->respondError($this->trans('BLIK OneClick is not enabled.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $cartId = (int) Tools::getValue('cart_id');
        $cart = $cartId > 0 ? new Cart($cartId) : $this->context->cart;
        if (!Validate::isLoadedObject($cart)) {
            $this->respondError($this->trans('Cart not found.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $currency = new Currency((int) $cart->id_currency);
        if ($currency->iso_code !== 'PLN') {
            $this->respondError($this->trans('BLIK supports PLN only.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->respondError($this->trans('Customer not found.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $activeAlias = $aliasService->findActiveAlias((int) $customer->id);
        if (!$activeAlias || empty($activeAlias['alias_uuid'])) {
            $this->respondError($this->trans('No active BLIK alias found. Please pay with BLIK code first.', [], 'Modules.Simpay.Shop'), 'NO_ACTIVE_ALIAS');
            return;
        }

        // Create order if not exists
        $orderId = (int) Order::getIdByCartId((int) $cart->id);
        if ($orderId <= 0) {
            $awaitingState = (int) Configuration::get(Simpay::CONFIG_OS_AWAITING);
            $this->module->validateOrder(
                (int) $cart->id,
                $awaitingState,
                $cart->getOrderTotal(),
                $this->trans('SimPay', [], 'Modules.Simpay.Shop'),
                null,
                null,
                (int) $currency->id,
                false,
                (string) $customer->secure_key
            );
            $orderId = (int) $this->module->currentOrder;
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            $this->respondError($this->trans('Order not found.', [], 'Modules.Simpay.Shop'));
            return;
        }

        SimPayLogger::setDefaultOrderId((int) $order->id);

        /** @var SimPayPaymentRequestBuilder $builder */
        $builder = $this->module->getService(SimPayPaymentRequestBuilder::class);
        $payload = $builder->build($cart, (string) $customer->secure_key, 'blik-level0', (int) $order->id);

        /** @var SimPaySDK $simpay */
        $simpay = $this->module->getService(SimPaySDK::class);

        try {
            $json = $simpay->client()->createTransaction($payload);
        } catch (\Throwable $e) {
            SimPayLogger::error($this->trans('OneClick payment initialization failed.', [], 'Modules.Simpay.Logs'), [
                'exception' => $e->getMessage(),
            ]);
            $this->respondError($this->trans('We could not initialize the payment.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $transactionId = (string) ($json['data']['transactionId'] ?? '');
        if ($transactionId === '') {
            $this->respondError($this->trans('Missing transaction ID.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $attemptService = $this->module->getService(SimPayPaymentAttemptService::class);
        $attemptService->registerAttempt($order, $transactionId, 'blik-oneclick', 'checkout');

        // Send OneClick request (no code, using alias uuid)
        $aliasPayload = $aliasService->buildOneClickAliasPayload($activeAlias);
        $blikAlias = \SimPay\SDK\BlikAlias::fromUuid(
            $aliasPayload['uuid'],
            $aliasPayload['label']
        );

        try {
            $simpay->client()->sendBlikOneClick($transactionId, $blikAlias);

            $attemptService->updateStatus($transactionId, 'blik_oneclick_sent', false);
            SimPayLogger::info($this->trans('BLIK OneClick payment sent.', [], 'Modules.Simpay.Logs'), [
                'transaction_id' => $transactionId,
            ]);

            $this->respondOk([
                'transaction_id' => $transactionId,
                'order_id' => (int) $order->id,
                'confirm_required' => true,
            ], $this->trans('Confirm the payment in your banking app.', [], 'Modules.Simpay.Shop'));
        } catch (\SimPay\SDK\Exception\ApiException $e) {
            $errorCode = $e->getApiCode() ?? "";


            $attemptService->updateStatus($transactionId, 'blik_oneclick_failed', false);
            SimPayLogger::error($this->trans('BLIK OneClick failed.', [], 'Modules.Simpay.Logs'), [
                'error_code' => $errorCode,
                'message' => $e->getMessage(),
            ]);

            $this->respondError(
                $this->translateBlikErrorCode($errorCode, $e->getMessage()),
                $errorCode
            );
        } catch (\Throwable $e) {
            $attemptService->updateStatus($transactionId, 'blik_oneclick_failed', false);
            $this->respondError($this->trans('BLIK OneClick request failed.', [], 'Modules.Simpay.Shop'));
        }
    }


    private function respondOk(array $data, string $message = ''): void
    {
        $this->ajaxRender(json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]));
    }

    private function respondError(string $message, string $errorCode = ''): void
    {
        $this->ajaxRender(json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
        ]));
    }

    private function handleStatus(): void
    {
        $token = (string) Tools::getValue('token');
        if ($token === '' || $token !== Tools::getToken('simpay')) {
            $this->respondError($this->trans('Invalid payment token.', [], 'Modules.Simpay.Shop'));
            return;
        }

        $cartId = (int) Tools::getValue('cart_id');
        $orderId = (int) Order::getIdByCartId($cartId);
        $transactionId = (string) Tools::getValue('transaction_id');

        if ($orderId <= 0 || $transactionId === '') {
            $this->respondOk(['status' => 'pending']);
            return;
        }

        /** @var SimPayPaymentAttemptService $attemptService */
        $attemptService = $this->module->getService(SimPayPaymentAttemptService::class);
        $attempt = $attemptService->findByTransactionId($transactionId);

        if (!$attempt || (int) ($attempt['id_order'] ?? 0) !== $orderId) {
            $this->respondOk(['status' => 'pending']);
            return;
        }

        $raw = strtolower((string) ($attempt['status'] ?? ''));

        $status = 'pending';
        if ($raw === 'transaction_paid') {
            $status = 'paid';
        } elseif ($raw === 'transaction_expired') {
            $status = 'expired';
        } elseif (in_array($raw, ['transaction_canceled', 'transaction_failure'], true)) {
            $status = 'rejected';
        }

        $order = new Order($orderId);
        $confirmUrl = '';
        if (Validate::isLoadedObject($order)) {
            $confirmUrl = $this->context->link->getPageLink(
                'order-confirmation',
                true,
                $this->context->language?->id,
                [
                    'id_cart' => (int) $order->id_cart,
                    'id_module' => (int) $this->module->id,
                    'id_order' => (int) $order->id,
                    'key' => (string) $order->secure_key,
                ]
            );
        }

        $this->respondOk([
            'status' => $status,
            'order_confirm_url' => $confirmUrl,
        ]);
    }
}
