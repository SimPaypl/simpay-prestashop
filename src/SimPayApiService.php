<?php
declare(strict_types=1);

namespace SimPaypl\PrestaShop;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SimPayApiService
{
    private ?string $bearerToken = null;
    private ?string $serviceId = null;

    public function __construct(?string $bearerToken, ?string $serviceId)
    {
        $this->bearerToken = $bearerToken;
        $this->serviceId = $serviceId;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function createPayment(array $parameters): ResponseInterface
    {
        return $this->sendRequest('POST', '/payment/' . $this->serviceId . '/transactions', $parameters);
    }

    public function getIps(): array
    {
        return json_decode($this->sendRequest('GET', '/ip')->getContent(), true)['data'];
    }

    public function getChannels(): array
    {
        return json_decode($this->sendRequest('GET', '/payment/' . $this->serviceId . '/channels')->getContent(), true)['data'];
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendBlikLevel0(string $transactionId, string $blikCode): ResponseInterface
    {
        $uri = '/payment/' . $this->serviceId . '/blik/level0/' . rawurlencode($transactionId);

        return $this->sendRequest('POST', $uri, [
            'json' => [
                'ticket' => [
                    'T6' => $blikCode,
                ],
            ],
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendRequest(string $method, string $uri, array $options = []): ResponseInterface
    {
        $options = array_merge($options, ['headers' => [
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-SIM-PLATFORM' => 'prestashop',
            'X-SIM-PLATFORM-VERSION' => _PS_VERSION_,
        ]]);

        $httpClient = HttpClient::createForBaseUri('https://api.simpay.pl');


        return $httpClient->request($method, $uri, $options);
    }
}