<?php

declare(strict_types=1);

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPayLogger;
use SimPaypl\PrestaShop\Service\SimPayPaymentAttemptService;
use SimPaypl\PrestaShop\Service\SimPayPaymentRequestBuilder;
use SimPaypl\PrestaShop\SimPayApiService;

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
        $attemptService = new SimPayPaymentAttemptService();

        /** @var SimPayPaymentRequestBuilder $builder */
        $builder = $this->get('prestashop.module.simpay.payment_request_builder');
        $payload = $builder->build($cart, (string) $customer->secure_key, 'blik-level0', (int) $order->id);

        /** @var SimPayApiService $paymentClient */
        $paymentClient = $this->get('prestashop.module.simpay.front.payment_client');

        try {
            $response = $paymentClient->createPayment(['json' => $payload]);
        } catch (\Throwable $e) {
            SimPayLogger::error($this->trans('BLIK payment initialization failed.', [], 'Modules.Simpay.Logs'), [
                'exception' => $e->getMessage(),
            ]);
            return $this->respondError($this->trans('We could not initialize the BLIK payment.', [], 'Modules.Simpay.Shop'));
        }

        if ($response->getStatusCode() !== 201) {
            SimPayLogger::error($this->trans('BLIK payment initialization failed.', [], 'Modules.Simpay.Logs'));
            return $this->respondError($this->trans('We could not initialize the BLIK payment.', [], 'Modules.Simpay.Shop'));
        }

        $json = json_decode($response->getContent(false), true);
        $transactionId = (string) ($json['data']['transactionId'] ?? '');

        if ($transactionId === '') {
            return $this->respondError($this->trans('Missing transaction ID.', [], 'Modules.Simpay.Shop'));
        }

        $attemptService->registerAttempt($order, $transactionId, 'blik-level0', 'checkout');

        /** @var SimPayApiService $paymentClient */
        $paymentClient = $this->get('prestashop.module.simpay.front.payment_client');

        $blikResult = $this->sendBlikLevel0($paymentClient, $transactionId, $blikCode);
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

    private function sendBlikLevel0($paymentClient, string $transactionId, string $blikCode): array
    {
        $serviceId = (string) Configuration::get(SimpayDataConfiguration::SERVICE_ID);
        if ($serviceId === '') {
            return ['success' => false, 'message' => $this->trans('Missing service ID.', [], 'Modules.Simpay.Shop')];
        }

        $response = $paymentClient->sendBlikLevel0($transactionId, $blikCode);
        $status = $response->getStatusCode();
        $body = (string) $response->getContent(false);

        if ($status === 204) {
            return ['success' => true];
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $errorCode = (string) ($decoded['errorCode'] ?? '');
            $message = (string) ($decoded['message'] ?? '');

            if ($errorCode !== '') {
                $message = $this->translateBlikErrorCode($errorCode, $message);
            }

            return [
                'success' => false,
                'message' => $message,
                'error_code' => $errorCode,
            ];
        }

        return ['success' => false, 'message' => $this->trans('BLIK request failed.', [], 'Modules.Simpay.Shop')];
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
        ];

        return $map[$code] ?? ($fallback !== '' ? $fallback : $this->trans('Invalid BLIK code.', [], 'Modules.Simpay.Shop'));
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
        $attemptService = new SimPayPaymentAttemptService();
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
