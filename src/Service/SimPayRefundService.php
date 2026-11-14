<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Service;

use Db;
use Order;
use SimPay\SDK\SimPay;
use SimPaypl\PrestaShop\Helper\SimPayLogger;

/**
 * Refunds are built from what the customer returns (products x quantity + optional shipping),
 * never from a free-typed amount. The amount is always recomputed here from the order data,
 * the browser only sends quantities.
 */
final class SimPayRefundService
{
    /** Refunds that reserve money/items: everything except rejected or failed ones. */
    private const ACTIVE_STATUSES = ['refund_new', 'refund_pending', 'refund_completed'];

    private ?bool $hasItemsColumn = null;

    public function __construct(
        private readonly SimPay $simpay
    ) {}

    /**
     * What can still be refunded for the order.
     *
     * Unit prices include the order-level discount (cart rules) proportionally, so refunding
     * every product + shipping never exceeds what the customer actually paid.
     *
     * @return array{
     *     items: array<int, array{id_order_detail: int, name: string, reference: string, unit_amount: float, qty_ordered: int, qty_refunded: int, qty_available: int}>,
     *     shipping: array{amount: float, refunded: bool, available: bool},
     *     paid: float,
     *     refunded: float,
     *     remaining: float,
     *     discount_percent: float
     * }
     */
    public function getRefundableData(Order $order): array
    {
        $paid = (float) $order->total_paid;
        $shippingPaid = $this->hasFreeShipping($order) ? 0.0 : (float) $order->total_shipping_tax_incl;
        $productsPaid = max(0.0, $paid - $shippingPaid - (float) $order->total_wrapping_tax_incl);
        $productsGross = (float) $order->total_products_wt;
        $factor = $productsGross > 0 ? min(1.0, $productsPaid / $productsGross) : 1.0;

        $refundedQty = $this->getRefundedQuantities((int) $order->id);
        $refundedTotal = $this->fromMinorUnits($this->getActiveRefundedMinor((int) $order->id));

        $items = [];
        foreach ($order->getProducts() as $product) {
            $detailId = (int) $product['id_order_detail'];
            $ordered = (int) $product['product_quantity'];
            $refunded = min($ordered, $refundedQty[$detailId] ?? 0);

            $items[] = [
                'id_order_detail' => $detailId,
                'name' => (string) $product['product_name'],
                'reference' => (string) ($product['product_reference'] ?? ''),
                'unit_amount' => round((float) $product['unit_price_tax_incl'] * $factor, 2),
                'qty_ordered' => $ordered,
                'qty_refunded' => $refunded,
                'qty_available' => $ordered - $refunded,
            ];
        }

        $shippingRefunded = !empty($refundedQty['shipping']);

        return [
            'items' => $items,
            'shipping' => [
                'amount' => round($shippingPaid, 2),
                'refunded' => $shippingRefunded,
                'available' => $shippingPaid > 0 && !$shippingRefunded,
            ],
            'paid' => $paid,
            'refunded' => $refundedTotal,
            'remaining' => max(0.0, round($paid - $refundedTotal, 2)),
            'discount_percent' => round((1 - $factor) * 100, 1),
        ];
    }

    /**
     * @param array<int|string, int|string> $quantities id_order_detail => quantity to refund
     *
     * @return array{success: bool, code: string, message?: string, refund_id?: string, amount?: float}
     */
    public function requestItemsRefund(Order $order, array $quantities, bool $refundShipping): array
    {
        $data = $this->getRefundableData($order);

        $selected = [];
        $amountMinor = 0;
        $selectsEverything = true;

        foreach ($data['items'] as $item) {
            $qty = (int) ($quantities[$item['id_order_detail']] ?? 0);

            if ($qty < 0 || $qty > $item['qty_available']) {
                SimPayLogger::warning('Refund validation failed: invalid quantity', [
                    'order' => (int) $order->id,
                    'detail' => $item['id_order_detail'],
                    'qty' => $qty,
                    'available' => $item['qty_available'],
                ]);

                return ['success' => false, 'code' => 'invalid_quantity'];
            }

            if ($qty !== $item['qty_available']) {
                $selectsEverything = false;
            }

            if ($qty > 0) {
                $selected[(string) $item['id_order_detail']] = $qty;
                $amountMinor += $this->toMinorUnits($item['unit_amount']) * $qty;
            }
        }

        if ($refundShipping) {
            if (!$data['shipping']['available']) {
                return ['success' => false, 'code' => 'invalid_quantity'];
            }
            $selected['shipping'] = 1;
            $amountMinor += $this->toMinorUnits($data['shipping']['amount']);
        } elseif ($data['shipping']['available']) {
            $selectsEverything = false;
        }

        if ($selected === [] || $amountMinor <= 0) {
            return ['success' => false, 'code' => 'nothing_selected'];
        }

        $remainingMinor = $this->toMinorUnits($data['remaining']);

        // Everything that is left -> refund exactly the remaining amount (absorbs per-line rounding)
        if ($selectsEverything || $amountMinor > $remainingMinor) {
            $amountMinor = $remainingMinor;
        }

        if ($amountMinor <= 0) {
            return ['success' => false, 'code' => 'invalid_amount'];
        }

        // First refund covering the whole payment -> "full" refund in SimPay, otherwise partial with amount
        $isFull = $data['refunded'] <= 0 && $amountMinor === $this->toMinorUnits($data['paid']);

        return $this->createRefund(
            $order,
            $isFull ? 'full' : 'partial',
            $this->fromMinorUnits($amountMinor),
            $selected
        );
    }

    /**
     * @param array<string, int> $items
     *
     * @return array{success: bool, code: string, message?: string, refund_id?: string, amount?: float}
     */
    private function createRefund(Order $order, string $type, float $amount, array $items): array
    {
        SimPayLogger::info('Refund requested', [
            'order' => (int) $order->id,
            'type' => $type,
            'amount' => $amount,
            'items' => $items,
        ]);

        $transactionId = $this->resolveTransactionIdForOrder((int) $order->id);
        if ($transactionId === '') {
            SimPayLogger::error('Refund failed: missing transaction', ['order' => (int) $order->id]);
            return ['success' => false, 'code' => 'missing_transaction'];
        }

        try {
            $payload = $this->simpay->client()->createRefund($transactionId, $type === 'partial' ? $amount : null);

            if (!is_array($payload) || empty($payload['success'])) {
                $message = is_array($payload) && !empty($payload['message']) ? (string) $payload['message'] : null;
                SimPayLogger::error('Refund API error', [
                    'order' => (int) $order->id,
                    'transaction' => $transactionId,
                    'message' => $message,
                ]);
                return ['success' => false, 'code' => 'api_error', 'message' => (string) $message];
            }

            $refundId = (string) ($payload['data']['refund_id'] ?? '');

            $now = date('Y-m-d H:i:s');
            $row = [
                'id_simpay_refund' => pSQL($refundId),
                'id_order' => (int) $order->id,
                'transaction_id' => pSQL($transactionId),
                'refund_type' => pSQL($type),
                'amount' => $this->toMinorUnits($amount),
                'status' => 'refund_new',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            // the refund already exists in SimPay - store it even if the items column is missing
            if ($this->ensureItemsColumn()) {
                $row['items'] = pSQL((string) json_encode($items));
            }
            Db::getInstance()->insert('simpay_refunds', $row);

            SimPayLogger::info('Refund created', [
                'order' => (int) $order->id,
                'refund_id' => $refundId,
                'transaction' => $transactionId,
                'amount' => $amount,
                'payload' => $payload,
            ]);

            return ['success' => true, 'code' => 'ok', 'refund_id' => $refundId, 'amount' => $amount];
        } catch (\Throwable $e) {
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

        $latestStatus = (string) Db::getInstance()->getValue(sprintf(
            'SELECT `status` FROM `%1$ssimpay_payment_attempt` WHERE `id_order` = %2$d ORDER BY `created_at` DESC',
            _DB_PREFIX_,
            $orderId
        ));

        if (!in_array($latestStatus, ['transaction_paid', 'transaction_confirmed'], true)) {
            return false;
        }

        // pending refunds reserve the amount too - otherwise the same money could be refunded twice
        return $this->getActiveRefundedMinor($orderId) < $this->toMinorUnits((float) $order->total_paid);
    }

    private function getActiveRefundedMinor(int $orderId): int
    {
        return (int) Db::getInstance()->getValue(sprintf(
            'SELECT COALESCE(SUM(`amount`), 0) FROM `%1$ssimpay_refunds` WHERE `id_order` = %2$d AND `status` IN (%3$s)',
            _DB_PREFIX_,
            $orderId,
            $this->activeStatusesSql()
        ));
    }

    /**
     * @return array<int|string, int> id_order_detail => refunded quantity, plus 'shipping' => 1 when refunded
     */
    private function getRefundedQuantities(int $orderId): array
    {
        if (!$this->ensureItemsColumn()) {
            return [];
        }

        $rows = (array) Db::getInstance()->executeS(sprintf(
            'SELECT `items` FROM `%1$ssimpay_refunds` WHERE `id_order` = %2$d AND `status` IN (%3$s) AND `items` IS NOT NULL',
            _DB_PREFIX_,
            $orderId,
            $this->activeStatusesSql()
        ));

        $totals = [];
        foreach ($rows as $row) {
            $items = json_decode((string) $row['items'], true);
            if (!is_array($items)) {
                continue;
            }
            foreach ($items as $key => $qty) {
                $key = $key === 'shipping' ? 'shipping' : (int) $key;
                $totals[$key] = ($totals[$key] ?? 0) + (int) $qty;
            }
        }

        return $totals;
    }

    /**
     * The `items` column is added by upgrade-1.3.0. Shops that already ran 1.3.0 before the column existed
     * (e.g. dev servers) never get the upgrade again - add it on the fly instead of breaking the order page.
     */
    private function ensureItemsColumn(): bool
    {
        if ($this->hasItemsColumn !== null) {
            return $this->hasItemsColumn;
        }

        $table = _DB_PREFIX_ . 'simpay_refunds';

        try {
            $exists = (bool) Db::getInstance()->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE \'items\'');
            if (!$exists) {
                Db::getInstance()->execute('ALTER TABLE `' . $table . '` ADD COLUMN `items` TEXT DEFAULT NULL AFTER `amount`');
                $exists = (bool) Db::getInstance()->executeS('SHOW COLUMNS FROM `' . $table . '` LIKE \'items\'');
            }
        } catch (\Throwable $e) {
            SimPayLogger::error('Refunds: cannot add items column', ['msg' => $e->getMessage()]);
            $exists = false;
        }

        return $this->hasItemsColumn = $exists;
    }

    private function hasFreeShipping(Order $order): bool
    {
        foreach ($order->getCartRules() as $rule) {
            if (!empty($rule['free_shipping'])) {
                return true;
            }
        }

        return false;
    }

    private function activeStatusesSql(): string
    {
        return implode(',', array_map(static fn (string $s): string => '\'' . pSQL($s) . '\'', self::ACTIVE_STATUSES));
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

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromMinorUnits(int $amount): float
    {
        return round($amount / 100, 2);
    }
}
