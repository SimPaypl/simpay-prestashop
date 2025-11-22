<?php
declare(strict_types=1);

namespace SimPaypl\PrestaShop;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SimPayApiService
{
    private ?string $bearerToken = null;

    public function __construct(?string $bearerToken)
    {
        $this->bearerToken = $bearerToken;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function createPayment(string $serviceId, array $parameters): ResponseInterface
    {
        return $this->sendRequest('POST', '/payment/' . $serviceId . '/transactions', $parameters);
    }

    public function getIps(): array
    {
        return json_decode($this->sendRequest('GET', '/ip')->getContent(), true)['data'];
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