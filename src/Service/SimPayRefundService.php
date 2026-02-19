<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Service;

use Db;
use Order;
use SimPaypl\PrestaShop\Helper\SimPayLogger;
use SimPaypl\PrestaShop\SimPayApiService;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class SimPayRefundService
{
    public function __construct(
        private readonly SimPayApiService $api
    ) {}

    public function requestRefund(Order $order, string $type, ?float $amount): array
    {
        SimPayLogger::info('Refund requested', [
            'order' => (int) $order->id,
            'type' => $type,
            'amount' => $amount,
        ]);

        $type = $this->normalizeType($type);
        if ($type === '') {
            SimPayLogger::warning('Refund validation failed: invalid type', ['order' => (int) $order->id]);
            return ['success' => false, 'code' => 'invalid_type'];
        }

        $maxAmount = (float) $order->total_paid;
        $amountValue = $type === 'full' ? $maxAmount : (float) $amount;

        if ($type === 'partial' && ($amountValue <= 0 || $amountValue > $maxAmount)) {
            SimPayLogger::warning('Refund validation failed: invalid amount', [
                'order' => (int) $order->id,
                'amount' => $amountValue,
                'max' => $maxAmount,
            ]);
            return ['success' => false, 'code' => 'invalid_amount'];
        }

        $transactionId = $this->resolveTransactionIdForOrder((int) $order->id);
        if ($transactionId === '') {
            SimPayLogger::error('Refund failed: missing transaction', ['order' => (int) $order->id]);
            return ['success' => false, 'code' => 'missing_transaction'];
        }

        try {
            $response = $this->api->createRefund($transactionId, $type === 'partial' ? $amountValue : null);
            $payload = json_decode($response->getContent(false), true);

            if (!is_array($payload) || empty($payload['success'])) {
                $message = is_array($payload) && !empty($payload['message']) ? (string) $payload['message'] : null;
                SimPayLogger::error('Refund API error', [
                    'order' => (int) $order->id,
                    'transaction' => $transactionId,
                    'message' => $message,
                ]);
                return ['success' => false, 'code' => 'api_error', 'message' => $message];
            }

            $refundId = (string) ($payload['data']['refund_id'] ?? '');

            $now = date('Y-m-d H:i:s');
            Db::getInstance()->insert('simpay_refunds', [
                'id_simpay_refund' => pSQL($refundId),
                'id_order' => (int) $order->id,
                'transaction_id' => pSQL($transactionId),
                'refund_type' => pSQL($type),
                'amount' => $this->toMinorUnits($amountValue),
                'status' => 'refund_new',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            SimPayLogger::info('Refund created', [
                'order' => (int) $order->id,
                'refund_id' => $refundId,
                'transaction' => $transactionId,
                'amount' => $amountValue,
                'payload' => $payload,
            ]);

            return ['success' => true, 'code' => 'ok', 'refund_id' => $refundId];
        } catch (TransportExceptionInterface|\Throwable $e) {
            SimPayLogger::error('Refund API exception', [
                'order' => (int) $order->id,
                'transaction' => $transactionId,
                'msg' => $e->getMessage(),
            ]);
            return ['success' => false, 'code' => 'api_error', 'message' => $e->getMessage()];
        }
    }

    public function findByOrderId(int $orderId): array
    {
        $sql = sprintf(
            'SELECT * FROM `%1$ssimpay_refunds` WHERE `id_order` = %2$d ORDER BY `created_at` DESC',
            _DB_PREFIX_,
            $orderId
        );

        return (array) Db::getInstance()->executeS($sql);
    }

    public function updateRefundStatus(string $refundId, string $status): bool
    {
        $data = [
            'status' => pSQL($status),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($refundId !== '') {
            return (bool) Db::getInstance()->update(
                'simpay_refunds',
                $data,
                'id_simpay_refund = \'' . pSQL($refundId) . '\''
            );
        }

        return false;
    }

    public function isRefundPossible(Order $order): bool
    {
        $orderId = (int) $order->id;
        $totalMinor = $this->toMinorUnits((float) $order->total_paid);

        $latestStatus = (string) Db::getInstance()->getValue(sprintf(
            'SELECT `status` FROM `%1$ssimpay_payment_attempt` WHERE `id_order` = %2$d ORDER BY `created_at` DESC',
            _DB_PREFIX_,
            $orderId
        ));

        if (!in_array($latestStatus, ['transaction_paid', 'transaction_confirmed'], true)) {
            return false;
        }

        $refundedMinor = (int) Db::getInstance()->getValue(sprintf(
            'SELECT COALESCE(SUM(`amount`), 0) FROM `%1$ssimpay_refunds` WHERE `id_order` = %2$d AND `status` = \'refund_completed\'',
            _DB_PREFIX_,
            $orderId
        ));

        return $refundedMinor < $totalMinor;
    }

    private function resolveTransactionIdForOrder(int $orderId): string
    {
        $sql = sprintf(
            'SELECT `transaction_id` FROM `%1$ssimpay_payment_attempt` WHERE `id_order` = %2$d ORDER BY `is_active` DESC, `created_at` DESC',
            _DB_PREFIX_,
            $orderId
        );

        $row = Db::getInstance()->getRow($sql);

        return isset($row['transaction_id']) ? (string) $row['transaction_id'] : '';
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, ['full', 'partial'], true) ? $type : '';
    }

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
