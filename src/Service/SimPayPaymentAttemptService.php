<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Service;

use Db;
use Order;
use OrderPayment;
use SimPaypl\PrestaShop\Helper\SimPayLogger;

final class SimPayPaymentAttemptService
{
    public function registerAttempt(Order $order, string $transactionId, ?string $channel, string $flowType): void
    {
        $now = date('Y-m-d H:i:s');
        $orderId = (int) $order->id;

        $this->deactivateScope($orderId, $now);

        Db::getInstance()->insert('simpay_payment_attempt', [
            'id_order' => $orderId,
            'id_cart' => (int) $order->id_cart,
            'transaction_id' => pSQL($transactionId),
            'channel' => $channel ? pSQL($channel) : null,
            'payment_type' => pSQL($flowType),
            'status' => 'transaction_new',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function findByTransactionId(string $transactionId): ?array
    {
        $sql = sprintf(
            'SELECT * FROM `%1$ssimpay_payment_attempt` WHERE `transaction_id` = \'%2$s\'',
            _DB_PREFIX_,
            pSQL($transactionId)
        );

        $row = Db::getInstance()->getRow($sql);

        return $row ?: null;
    }

    public function findByOrderId(int $orderId): array
    {
        $sql = sprintf(
            'SELECT * FROM `%1$ssimpay_payment_attempt` WHERE `id_order` = %2$d ORDER BY `created_at` DESC',
            _DB_PREFIX_,
            $orderId
        );

        return (array) Db::getInstance()->executeS($sql);
    }

    public function updateStatus(string $transactionId, string $status, bool $deactivate = false, ?string $channel = null): void
    {
        $data = [
            'status' => pSQL($status),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($deactivate) {
            $data['is_active'] = 0;
        }

        if (is_string($channel) && $channel !== '') {
            $data['channel'] = pSQL($channel);
        }

        Db::getInstance()->update(
            'simpay_payment_attempt',
            $data,
            'transaction_id = \'' . pSQL($transactionId) . '\''
        );
    }

    private function deactivateScope(int $orderId, string $now): void
    {
        Db::getInstance()->update(
            'simpay_payment_attempt',
            [
                'is_active' => 0,
                'updated_at' => $now,
            ],
            'id_order = ' . $orderId
        );
    }

    /**
     * Update native PrestaShop order_payment with SimPay transaction ID.
     * Makes the transaction visible to external integrations (BaseLinker, ERP, etc.)
     */
    public function syncOrderPaymentTransactionId(Order $order, string $transactionId): void
    {
        if ($transactionId === '') {
            return;
        }

        try {
            $payments = $order->getOrderPaymentCollection();
            if ($payments && $payments->count() > 0) {
                /** @var OrderPayment $payment */
                $payment = $payments->getLast();
                $payment->transaction_id = $transactionId;
                $payment->save();
            }
        } catch (\Throwable $e) {
            SimPayLogger::warning('Failed to update order_payment transaction_id', [
                'order' => (int) $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
