<?php

declare(strict_types=1);

use Context;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShopLogger;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPayLogger;
use SimPaypl\PrestaShop\Helper\SimPaySignatureValidator;

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
            SimPayLogger::respond(405, 'Method not allowed', 'NOT_POST');
        }

        $raw = Tools::file_get_contents('php://input');

        if (!is_string($raw) || $raw === '') {
            SimPayLogger::respond(400, 'Invalid payload', 'EMPTY_RAW');
        }

        // IP allowlist check (only log result)
        if ((bool) Configuration::get(SimpayDataConfiguration::IPN_CHECK_IP)) {
            /** @var \SimPaypl\PrestaShop\SimPayApiService $paymentClient */
            $paymentClient = $this->get('prestashop.module.simpay.front.payment_client');

            $ips = (array) $paymentClient->getIps();
            $ok = in_array(Tools::getRemoteAddr(), $ips, true);
            
            if (!$ok) {
                SimPayLogger::respond(403, 'Invalid IP', 'BAD_IP');
            }
        }

        // Version check from UA: "SimPay-IPN/2.0"
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $parts = explode('/', $ua, 2);
        $version = $parts[1] ?? 'N/A';

        if ($version !== '2.0') {
            SimPayLogger::respond(400, 'IPN version is not supported', 'BAD_VERSION', ['v' => $version]);
        }

        $payload = json_decode($raw, true);
        $okJson = is_array($payload);

        if (!$okJson) {
            SimPayLogger::respond(400, 'Invalid JSON', 'BAD_JSON');
        }

        if (!$this->validateRequest($payload)) {
            SimPayLogger::respond(422, 'Validation failed', 'BAD_FIELDS', [
                'missing' => implode(',', $this->missingFields($payload)),
            ]);
        }

        $sigKey = (string) Configuration::get(SimpayDataConfiguration::SERVICE_IPN_SIGNATURE_KEY);
        $signatureOk = (new SimPaySignatureValidator())->isValid($payload, $sigKey);

        if (!$signatureOk) {
            SimPayLogger::respond(409, 'Invalid signature', 'BAD_SIGNATURE');
        }

        $type = (string) ($payload['type'] ?? '');

        $status = (string) ($payload['status'] ?? '');
        $repaymentEnabled = (bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED);
        $newState = null;

        if ($type === 'transaction:status_changed') {

            $data = (array) ($payload['data'] ?? []);
            $this->handleTransactionStatusChangedEvent($data);
        }

        SimPayLogger::respond(200, 'OK', 'DONE');
    }

    private function handleTransactionStatusChangedEvent(array $payload): void
    {
        $cartId = (int) ($payload['control'] ?? 0);
        if ($cartId <= 0) {
            SimPayLogger::error('ERROR invalid control', ['control' => $payload['control'] ?? null]);
            http_response_code(400);
            die('Invalid control');
        }

        $order = Order::getByCartId($cartId);
        if (!$order || !Validate::isLoadedObject($order)) {
            SimPayLogger::error('ERROR order not found', ['cart' => $cartId]);
            http_response_code(400);
            die('Order not found');
        }

        $incoming = (float) ($payload['amount']['original_value'] ?? 0);
        $expected = (float) $order->getTotalPaid();

        if ($incoming > 0 && $this->isLessThan($incoming, $expected)) {
            SimPayLogger::error('ERROR amount too low');
            http_response_code(402);
            die('Invalid amount');
        }

        if (!$this->module->isUpdatableState((int) $order->current_state)) {
            SimPayLogger::info('SKIP order already processed');
            die('OK');
        }

        $repaymentEnabled = (bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED);
        $status = (string) ($payload['status'] ?? '');
        $newState = null;

        switch ($status) {
            case 'transaction_paid':
                $newState = (int) Configuration::get('PS_OS_PAYMENT');
                break;

            case 'transaction_canceled':
                $newState = (int) Configuration::get('PS_OS_CANCELED');
                break;

            case 'transaction_failure':
            case 'transaction_fraud':
                $newState = (int) Configuration::get('PS_OS_ERROR');
                break;

            case 'transaction_expired':
                $newState = (int) Configuration::get(Simpay::CONFIG_OS_EXPIRED);
                break;
            default:
                SimPayLogger::warning('SKIP unknown status', ['status' => $status]);
                die('OK');
        }

        try {
            $history = new OrderHistory();
            $history->id_order = (int) $order->id;
            $history->changeIdOrderState($newState, (int) $order->id, true);

            $orderState = new OrderState($newState);
            $sendEmail = Validate::isLoadedObject($orderState) ? (bool) $orderState->send_email : false;
            $history->addWithemail($sendEmail, [], Context::getContext());

            SimPayLogger::info('SUCCESS state changed', [
                'order' => (int) $order->id,
                'new_state' => $newState,
            ]);
        } catch (\Throwable $e) {
            SimPayLogger::error('ERROR exception', ['msg' => $e->getMessage()]);
            http_response_code(500);
            die('Order update failed');
        }

        die('OK');
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
}

