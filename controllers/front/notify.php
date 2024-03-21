<?php

declare(strict_types=1);

use Simpay\Model\Request\Amount;
use Simpay\Model\Request\CallbackReturnUrl;
use Simpay\Model\Request\CreatePayment;
use Simpay\Model\Request\ServiceId;
use Simpay\Model\Request\Currency;
use Simpay\PaymentInterface;

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
        $jsonPayload = Tools::file_get_contents('php://input');

        if (!is_string($jsonPayload)) {
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'Invalid payload, expected JSON']));
        }

        /** @var array{
         *     id: string,
         *     service_id: string,
         *     status: string,
         *     amount: array{
         *          value: float,
         *          currency: string,
         *          commission: float,
         *     },
         *     control: string,
         *     channel: string,
         *     environment: string,
         *     signature: string
         * }|bool|null $payload
         */
        $payload = json_decode($jsonPayload, true);

        if (!is_array($payload)) {
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'Invalid payload, expected JSON']));
        }

        $signatureValues = [];
        array_walk_recursive($payload, function (mixed $item, string $key) use (&$signatureValues) {
            if ($key === 'signature') {
                return;
            }

            $signatureValues[] = (string) $item;
        });

        $signatureValues[] = Configuration::get('SIMPAY_SERVICE_IPN_SIGNATURE_KEY');
        $signatureValues = implode('|', $signatureValues);
        $signatureHash = hash('sha256', $signatureValues);

        if ($payload['signature'] !== $signatureHash) {
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'Invalid signature']));
        }

        $order = Order::getByCartId((int) $payload['control']);

        if (null === $order) {
            http_response_code(400);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'Order not found']));
        }

        if ((int) Configuration::get(Simpay::CONFIG_OS_AWAITING) === $order->getCurrentState()) {

            $orderStatusId = (int) match($payload['status']) {
                'transaction_paid' => Configuration::get('PS_OS_PAYMENT'),
                default => Configuration::get('PS_OS_ERROR'),
            };

            $orderHistory = new OrderHistory();
            $orderHistory->id_order = (int) $order->id;
            $orderHistory->changeIdOrderState($orderStatusId, (int) $order->id, true);
            $orderHistory->save();
        }


        http_response_code(200);
        header('Content-Type: application/json');
        die(json_encode(['status' => 'ok']));
    }
}
