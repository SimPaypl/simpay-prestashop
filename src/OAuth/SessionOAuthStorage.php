<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\OAuth;

use SimPay\SDK\OAuth\OAuthStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Keeps the SimPay OAuth flow in the back-office employee's Symfony session.
 */
final class SessionOAuthStorage implements OAuthStorageInterface
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function get(string $key): ?array
    {
        $value = $this->session->get($key);

        return is_array($value) ? $value : null;
    }

    public function set(string $key, array $value): void
    {
        $this->session->set($key, $value);
    }

    public function remove(string $key): void
    {
        $this->session->remove($key);
    }
}
