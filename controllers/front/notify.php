<?php

declare(strict_types=1);

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPayLogger;
use SimPaypl\PrestaShop\Helper\SimPaySignatureValidator;
use SimPaypl\PrestaShop\Service\SimPayPaymentAttemptService;

final class SimpayNotifyModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    public function init(): void
    {
        parent::init();
    }

    public function postProcess(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            SimPayLogger::respond(
                405,
                $this->trans('Method not allowed', [], 'Modules.Simpay.Logs'),
                'NOT_POST'
            );
        }

        $raw = Tools::file_get_contents('php://input');
        $payload = json_decode($raw, true);
        $okJson = is_array($payload);

        SimPayLogger::info(
            $this->trans('Webhook received', [], 'Modules.Simpay.Logs'),
            ['ok_json' => $okJson]
        );

        if ($okJson && isset($payload['data']) && is_array($payload['data'])) {
            $cartId = (int) ($payload['data']['control'] ?? 0);
            if ($cartId > 0) {
                $order = Order::getByCartId($cartId);
                if ($order && Validate::isLoadedObject($order)) {
                    $this->orderId = (int) $order->id;
                    SimPayLogger::setDefaultOrderId($this->orderId);
                }
            }

            SimPayLogger::info($this->trans('Webhook data received', [], 'Modules.Simpay.Logs'), [
                'status' => $payload['data']['status'] ?? 'N/A',
                'cart' => $payload['data']['control'] ?? 'N/A',
            ]);
        }

        if (!is_string($raw) || $raw === '') {
            SimPayLogger::respond(
                400,
                $this->trans('Invalid payload', [], 'Modules.Simpay.Logs'),
                'EMPTY_RAW'
            );
        }

        // IP allowlist check (only log result)
        if ((bool) Configuration::get(SimpayDataConfiguration::IPN_CHECK_IP)) {
            /** @var \SimPaypl\PrestaShop\SimPayApiService $paymentClient */
            $paymentClient = $this->get('prestashop.module.simpay.front.payment_client');

            $ips = (array) $paymentClient->getIps();
            $ok = in_array(Tools::getRemoteAddr(), $ips, true);

            if (!$ok) {
                SimPayLogger::respond(
                    403,
                    $this->trans('Invalid IP', [], 'Modules.Simpay.Logs'),
                    'BAD_IP'
                );
            }
        }

        // Version check from UA: "SimPay-IPN/2.0"
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $parts = explode('/', $ua, 2);
        $version = $parts[1] ?? 'N/A';

        if ($version !== '2.0') {
            SimPayLogger::respond(
                400,
                $this->trans('IPN version is not supported', [], 'Modules.Simpay.Logs'),
                'BAD_VERSION',
                ['v' => $version]
            );
        }

        if (!$okJson) {
            SimPayLogger::respond(
                400,
                $this->trans('Invalid JSON', [], 'Modules.Simpay.Logs'),
                'BAD_JSON'
            );
        }

        if (!$this->validateRequest($payload)) {
            SimPayLogger::respond(
                422,
                $this->trans('Validation failed', [], 'Modules.Simpay.Logs'),
                'BAD_FIELDS',
                ['missing' => implode(',', $this->missingFields($payload))]
            );
        }

        $sigKey = (string) Configuration::get(SimpayDataConfiguration::SERVICE_IPN_SIGNATURE_KEY);
        $signatureOk = (new SimPaySignatureValidator())->isValid($payload, $sigKey);

        if (!$signatureOk) {
            SimPayLogger::respond(
                409,
                $this->trans('Invalid signature', [], 'Modules.Simpay.Logs'),
                'BAD_SIGNATURE'
            );
        }

        $type = (string) ($payload['type'] ?? '');
        SimPayLogger::info(
            $this->trans('Webhook type received', [], 'Modules.Simpay.Logs'),
            [
                'type' => $type,
                'status' => $payload['data']['status'] ?? 'N/A'
            ]
        );

        if ($type === 'transaction:status_changed') {
            $data = (array) ($payload['data'] ?? []);
            SimPayLogger::info(
                $this->trans('Transaction status change event received', [], 'Modules.Simpay.Logs'),
                ['status' => $data['status'] ?? 'N/A']
            );
            $this->handleTransactionStatusChangedEvent($data);
        }

        SimPayLogger::respond(200, 'OK', 'DONE');
    }

    private function handleTransactionStatusChangedEvent(array $payload): void
    {
        $transactionId = (string) ($payload['id'] ?? '');
        if ($transactionId === '') {
            SimPayLogger::respond(
                400,
                $this->trans('Missing transaction id', [], 'Modules.Simpay.Logs'),
                'NO_TRANSACTION_ID'
            );
        }

        $attemptService = new SimPayPaymentAttemptService();
        $attempt = $attemptService->findByTransactionId($transactionId);

        if (!$attempt) {
            SimPayLogger::warning(
                $this->trans('Unknown transaction, event ignored', [], 'Modules.Simpay.Logs'),
                ['transaction' => $transactionId]
            );
            SimPayLogger::respond(200, 'OK', 'UNKNOWN_TRANSACTION');
        }

        $order = $this->resolveOrderForAttempt($attempt);
        if (!$order || !Validate::isLoadedObject($order)) {
            SimPayLogger::error(
                $this->trans('Order not found for transaction', [], 'Modules.Simpay.Logs'),
                ['transaction' => $transactionId]
            );
            SimPayLogger::respond(
                400,
                $this->trans('Order not found', [], 'Modules.Simpay.Logs'),
                'ORDER_NOT_FOUND'
            );
        }

        if (!$this->module->isUpdatableState((int) $order->current_state)) {
            SimPayLogger::info(
                $this->trans('Order already processed, event ignored', [], 'Modules.Simpay.Logs'),
                ['order' => (int) $order->id]
            );
            SimPayLogger::respond(200, 'OK', 'ORDER_ALREADY_PROCESSED');
        }

        $status = (string) ($payload['status'] ?? '');
        $finalChannel = (string) ($payload['payment']['channel'] ?? '');

        SimPayLogger::info(
            $this->trans('Transaction status received', [], 'Modules.Simpay.Logs'),
            ['status' => $status, 'transaction' => $transactionId]
        );

        if (!(bool) $attempt['is_active']) {
            $attemptService->updateStatus($transactionId, $status, $this->isTerminalStatus($status), $finalChannel);
            SimPayLogger::info(
                $this->trans('Inactive attempt, event ignored', [], 'Modules.Simpay.Logs'),
                ['transaction' => $transactionId]
            );
            SimPayLogger::respond(200, 'OK', 'INACTIVE_ATTEMPT');
        }

        $newState = $this->mapStatusToOrderState($status);
        if (null === $newState) {
            SimPayLogger::warning(
                $this->trans('Unknown status, event ignored', [], 'Modules.Simpay.Logs'),
                ['status' => $status]
            );
            SimPayLogger::respond(200, 'OK', 'UNKNOWN_STATUS');
        }

        if ($attempt['status'] === $status) {
            SimPayLogger::info(
                $this->trans('Duplicate status, event ignored', [], 'Modules.Simpay.Logs'),
                ['status' => $status, 'transaction' => $transactionId]
            );
            SimPayLogger::respond(200, 'OK', 'DUPLICATE_STATUS');
        }

        $incoming = (float) ($payload['amount']['original_value'] ?? 0);
        $expected = (float) $order->getTotalPaid();
        if ($incoming > 0 && $this->isLessThan($incoming, $expected)) {
            SimPayLogger::error(
                $this->trans('Amount too low', [], 'Modules.Simpay.Logs'),
                ['incoming' => $incoming, 'expected' => $expected]
            );
            SimPayLogger::respond(
                402,
                $this->trans('Invalid amount', [], 'Modules.Simpay.Logs'),
                'AMOUNT_LOW'
            );
        }

        try {
            $history = new OrderHistory();
            $history->id_order = (int) $order->id;
            $history->changeIdOrderState($newState, (int) $order->id, true);

            $orderState = new OrderState($newState);
            if (
                (Validate::isLoadedObject($orderState) && $orderState->send_email)
                && (bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED)
            ) {
                $history->addWithemail(true, [], Context::getContext());
            } else {
                $history->add();
            }

            $attemptService->updateStatus($transactionId, $status, $this->isTerminalStatus($status), $finalChannel);

            SimPayLogger::info(
                $this->trans('Order status updated successfully', [], 'Modules.Simpay.Logs'),
                ['order' => (int) $order->id, 'new_state' => $newState]
            );
        } catch (\Throwable $e) {
            $context = [
                'order' => (int) $order->id,
                'status' => $status,
                'msg' => $e->getMessage(),
            ];

            SimPayLogger::error(
                $this->trans('Order update failed', [], 'Modules.Simpay.Logs'),
                $context
            );
            PrestaShopLogger::addLog(
                sprintf('SimPay notify failure (order #%d): %s', (int) $order->id, $e->getMessage()),
                3,
                0,
                'Order',
                (int) $order->id,
                true
            );

            SimPayLogger::respond(
                500,
                $this->trans('Order update failed', [], 'Modules.Simpay.Logs'),
                'ORDER_UPDATE_FAILED',
                $context
            );
        }
    }

    private function missingFields(array $payload): array
    {
        $required = ['type', 'notification_id', 'date', 'data', 'signature'];
        $missing = [];

        foreach ($required as $f) {
            if (!array_key_exists($f, $payload) || $payload[$f] === null || $payload[$f] === '') {
                $missing[] = $f;
            }
        }

        return $missing;
    }

    private function validateRequest(?array $payload): bool
    {
        if (empty($payload)) {
            return false;
        }

        foreach (['type', 'notification_id', 'date', 'data', 'signature'] as $field) {
            if (empty($payload[$field])) {
                return false;
            }
        }

        return true;
    }

    private function isLessThan(float $a, float $b, int $precision = 2): bool
    {
        $factor = 10 ** $precision;

        $ai = (int) round($a * $factor);
        $bi = (int) round($b * $factor);

        return $ai < $bi;
    }

    private function resolveOrderForAttempt(array $attempt): ?Order
    {
        $orderId = (int) ($attempt['id_order'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $order = new Order($orderId);

        return Validate::isLoadedObject($order) ? $order : null;
    }

    private function mapStatusToOrderState(string $status): ?int
    {
        return match ($status) {
            'transaction_paid', 'transaction_confirmed' => (int) Configuration::get('PS_OS_PAYMENT'),
            'transaction_canceled' => (int) Configuration::get('PS_OS_CANCELED'),
            'transaction_fraud', 'transaction_failure' => (int) Configuration::get('PS_OS_ERROR'),
            'transaction_expired' => (int) Configuration::get(Simpay::CONFIG_OS_EXPIRED),
            default => null,
        };
    }

    private function isTerminalStatus(string $status): bool
    {
        return in_array($status, [
            'transaction_paid',
            'transaction_canceled',
            'transaction_failure',
            'transaction_fraud',
            'transaction_expired',
        ], true);
    }
}
