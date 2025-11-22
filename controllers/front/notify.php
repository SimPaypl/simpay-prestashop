<?php

declare(strict_types=1);

use Simpay\Model\Request\Amount;
use Simpay\Model\Request\CallbackReturnUrl;
use Simpay\Model\Request\CreatePayment;
use Simpay\Model\Request\Currency;
use Simpay\Model\Request\ServiceId;
use Simpay\PaymentInterface;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPaySignatureValidator;

final class SimpayNotifyModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    public function init(): void
    {
        parent::init();
        // validate module is enabled and configured correctly
    }

    public function postProcess(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Method not allowed');
        }

        if ((bool)Configuration::get(SimpayDataConfiguration::IPN_CHECK_IP)) {
            /** @var \SimPaypl\PrestaShop\SimPayApiService $paymentClient */
            $paymentClient = $this->get('prestashop.module.simpay.front.payment_client');

            $ips = $paymentClient->getIps();
            if (!in_array(Tools::getRemoteAddr(), $ips)) {
                PrestaShopLogger::addLog(
                    'SimPayPayment: Unauthorized IP address in IPN: ' . Tools::getRemoteAddr(),
                    3,
                    0,
                    'IPN',
                    null,
                    true,
                );
                http_response_code(403);
                die('Invalid IP');
            }
        }

        $raw = Tools::file_get_contents('php://input');
        if (!is_string($raw)) {
            http_response_code(400);
            die('Invalid payload');
        }
        $userAgent = explode('/', $_SERVER['HTTP_USER_AGENT'], 2);
        if (empty($userAgent[1]) || $userAgent[1] !== '2.0') {
            PrestaShopLogger::addLog(
                'SimPayPayment: Got unsupported version of IPN (' . $userAgent[1] ?? 'N/A' . ')',
                3,
                0,
                'IPN',
                null,
                true,
            );
            http_response_code(400);
            die('IPN version is not supported');
        }

        $payload = json_decode($raw, true);

        if (!$this->validateRequest($payload)) {
            http_response_code(422);
            die('Validation failed');
        }

        if (!(new SimPaySignatureValidator())->isValid($payload, Configuration::get(SimpayDataConfiguration::SERVICE_IPN_SIGNATURE_KEY))) {
            http_response_code(409);
            die('Invalid signature');
        }

        if ($payload['type'] === 'transaction:status_changed') {
            $this->handleTransactionStatusChangedEvent($payload['data']);
        }

        die('OK');
    }

    private function handleTransactionStatusChangedEvent(array $payload)
    {
        $order = Order::getByCartId((int)$payload['control']);
        if (!$order) {
            PrestaShopLogger::addLog(
                'SimPayPayment: Order not found: ' . $payload['control'],
                3,
                0,
                'IPN',
                null,
                true,
            );
            http_response_code(400);
            die('Order not found');
        }

        if ($this->isLessThan((float)$payload['amount']['original_value'], $order->getTotalPaid())) {
            PrestaShopLogger::addLog(
                'SimPayPayment: Invalid amount for order: ' . $payload['control'] . ' - ' . $payload['amount']['original_value'] . ' < ' . $order->getTotalPaid(),
                3,
                0,
                'IPN',
                null,
                true,
            );
            http_response_code(402);
            die('Invalid amount');
        }

        if ((int)Configuration::get(Simpay::CONFIG_OS_AWAITING) !== $order->getCurrentState()) {
            die('OK');
        }

        $changeOrderStatus = false;
        $newOrderStatus = null;
        if ($payload['status'] === 'transaction_paid') {
            $changeOrderStatus = true;
            $newOrderStatus = Configuration::get('PS_OS_PAYMENT');
        } else if ($payload['status'] === 'transaction_canceled') {
            $changeOrderStatus = true;
            $newOrderStatus = Configuration::get('PS_OS_CANCELED');
        } else if (in_array($payload['status'], ['transaction_failure', 'transaction_expired', 'transaction_fraud'])) {
            $changeOrderStatus = true;
            $newOrderStatus = Configuration::get('PS_OS_ERROR');
        }

        if ($changeOrderStatus) {
            $orderHistory = new OrderHistory();
            $orderHistory->id_order = (int)$order->id;
            $orderHistory->changeIdOrderState($newOrderStatus, (int)$order->id, true);
            $orderHistory->save();
        }

        die('OK');
    }

    private function validateRequest(array|null $payload): bool
    {
        if (empty($payload)) {
            return false;
        }

        $requiredFields = [
            'type',
            'notification_id',
            'date',
            'data',
            'signature',
        ];

        foreach ($requiredFields as $field) {
            if (empty($payload[$field])) {
                return false;
            }
        }

        return true;
    }

    // $a < $b => true
    // $a >= $b => false
    private function isLessThan(float $a, float $b, int $precision = 2): bool
    {
        if (bccomp((string)$a, (string)$b, $precision) == 0) {
            return false;
        }

        return bccomp((string)$a, (string)$b, $precision) < 0;
    }
}
