<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Controller;

use Configuration;
use Link;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopLogger;
use SimPay\SDK\Exception\ApiException;
use SimPay\SDK\OAuth\OAuthConnector;
use SimPay\SDK\OAuth\OAuthException;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPayChannelCache;
use SimPaypl\PrestaShop\OAuth\SessionOAuthStorage;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * "Connect with SimPay" endpoints. The flow itself lives in simpay/ecommerce (OAuthConnector);
 * this controller only wires it to PrestaShop: session, routing, configuration and messages.
 */
final class SimpayOAuthAdminController extends FrameworkBundleAdminController
{
    /** OAuth client registered by SimPay for the PrestaShop plugin (public client, PKCE, skips redirect_uri validation). */
    public const OAUTH_CLIENT_ID = '01a1c1b3-a913-7337-8ba8-b40ba7a93abf';

    private SimPayChannelCache $simPayChannelCache;

    public function __construct(SimPayChannelCache $simPayChannelCache)
    {
        $this->simPayChannelCache = $simPayChannelCache;
    }

    public static function createConnector(Request $request): OAuthConnector
    {
        return OAuthConnector::create(self::OAUTH_CLIENT_ID, new SessionOAuthStorage($request->getSession()));
    }

    /**
     * Builds the OAuth callback URL from the shop's configured domain instead of the current request.
     *
     * Behind a reverse proxy / tunnel / load balancer terminating TLS, PHP sees plain HTTP and Symfony
     * (no trusted proxies in PrestaShop) would generate an http:// URL, which SimPay rejects.
     */
    public static function buildRedirectUri(string $callbackPath): string
    {
        $secure = (bool) Configuration::get('PS_SSL_ENABLED') || \Tools::usingSecureMode();
        $base = $secure ? \Tools::getShopDomainSsl(true) : \Tools::getShopDomain(true);

        if ($secure) {
            // getShopDomainSsl() falls back to http:// when PS_SSL_ENABLED is off but the request is secure
            $base = (string) preg_replace('#^http://#i', 'https://', $base);
        }

        return rtrim($base, '/') . '/' . ltrim($callbackPath, '/');
    }

    public static function getShopUrl(): string
    {
        return \Tools::getShopDomainSsl(true);
    }

    public function connectAction(Request $request): RedirectResponse
    {
        $redirectUri = self::buildRedirectUri(
            $this->generateUrl('simpay_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_PATH)
        );

        try {
            return new RedirectResponse(self::createConnector($request)->start($redirectUri));
        } catch (\Throwable $e) {
            return $this->failAndRedirect($e);
        }
    }

    public function callbackAction(Request $request): RedirectResponse
    {
        try {
            self::createConnector($request)->handleCallback($request->query->all());
        } catch (\Throwable $e) {
            return $this->failAndRedirect($e);
        }

        // service picker is rendered on the configuration page from the pending connection
        return $this->redirectToRoute('simpay_configuration');
    }

    public function provisionAction(Request $request): RedirectResponse
    {
        try {
            $result = self::createConnector($request)->connect(
                (string) $request->request->get('service_id', ''),
                (new Link())->getModuleLink('simpay', 'notify', [], true),
                'PrestaShop ' . (string) parse_url(self::getShopUrl(), PHP_URL_HOST)
            );
        } catch (\Throwable $e) {
            return $this->failAndRedirect($e);
        }

        Configuration::updateValue(SimpayDataConfiguration::API_PASSWORD, $result->apiKey);
        Configuration::updateValue(SimpayDataConfiguration::SERVICE_ID, $result->serviceId);
        Configuration::updateValue(SimpayDataConfiguration::SERVICE_IPN_SIGNATURE_KEY, $result->ipnSignatureKey);
        Configuration::updateValue(SimpayDataConfiguration::AUTH_METHOD, SimpayDataConfiguration::AUTH_METHOD_OAUTH);
        Configuration::updateValue(SimpayDataConfiguration::OAUTH_ACCOUNT_EMAIL, $result->accountEmail);
        Configuration::updateValue(SimpayDataConfiguration::OAUTH_SERVICE_NAME, $result->serviceName);

        // channels are bound to the service - force re-fetch with new credentials on next read
        $this->simPayChannelCache->clear();

        $this->addFlash('success', $this->trans('The shop has been connected to SimPay service "%service%". API key, IPN URL and IPN signature key were configured automatically.', 'Modules.Simpay.Admin', [
            '%service%' => $result->serviceName,
        ]));

        return $this->redirectToRoute('simpay_configuration');
    }

    /**
     * Removes OAuth-provisioned credentials from the shop.
     * The API has no endpoint for deleting API keys / revoking access, so the key stays in the SimPay panel.
     */
    public function disconnectAction(Request $request): RedirectResponse
    {
        self::createConnector($request)->cancel();

        foreach ([
            SimpayDataConfiguration::API_PASSWORD,
            SimpayDataConfiguration::SERVICE_ID,
            SimpayDataConfiguration::SERVICE_IPN_SIGNATURE_KEY,
            SimpayDataConfiguration::OAUTH_ACCOUNT_EMAIL,
            SimpayDataConfiguration::OAUTH_SERVICE_NAME,
        ] as $key) {
            Configuration::updateValue($key, '');
        }
        Configuration::updateValue(SimpayDataConfiguration::AUTH_METHOD, SimpayDataConfiguration::AUTH_METHOD_OAUTH);

        $this->simPayChannelCache->clear();

        $this->addFlash('success', $this->trans('The shop has been disconnected from SimPay. SimPay payments will not work until you connect again or enter the credentials manually.', 'Modules.Simpay.Admin'));

        return $this->redirectToRoute('simpay_configuration');
    }

    public function cancelAction(Request $request): RedirectResponse
    {
        self::createConnector($request)->cancel();

        return $this->redirectToRoute('simpay_configuration');
    }

    private function failAndRedirect(\Throwable $e): RedirectResponse
    {
        PrestaShopLogger::addLog('[SimPay] OAuth error: ' . $e->getMessage(), 3, null, 'SimPay');

        $this->addFlash('error', $this->getErrorMessage($e));

        return $this->redirectToRoute('simpay_configuration');
    }

    private function getErrorMessage(\Throwable $e): string
    {
        if ($e instanceof OAuthException) {
            return match ($e->getReason()) {
                OAuthException::REDIRECT_URI_NOT_ALLOWED => $this->trans('Connecting via SimPay requires the shop back office to be served over HTTPS. Enter the credentials manually instead.', 'Modules.Simpay.Admin'),
                OAuthException::ACCESS_DENIED => $this->trans('Connection with SimPay was cancelled.', 'Modules.Simpay.Admin'),
                OAuthException::AUTHORIZATION_FAILED => $this->trans('SimPay authorization failed: %error%', 'Modules.Simpay.Admin', [
                    '%error%' => (string) $e->getDetails(),
                ]),
                OAuthException::INVALID_STATE => $this->trans('Invalid or expired SimPay authorization. Please try connecting again.', 'Modules.Simpay.Admin'),
                OAuthException::NO_PAYMENT_SERVICES => $this->trans('No active payment services were found on your SimPay account. Create a payment service in the SimPay panel first.', 'Modules.Simpay.Admin'),
                OAuthException::EXPIRED => $this->trans('The SimPay authorization has expired. Please connect again.', 'Modules.Simpay.Admin'),
                OAuthException::UNKNOWN_SERVICE => $this->trans('Please select a payment service.', 'Modules.Simpay.Admin'),
                OAuthException::UNSUPPORTED_IPN_VERSION => $this->trans('The selected service uses IPN version %version%, but this module supports IPN 2.0 only. Change the IPN version to 2.0 in the service settings in the SimPay panel, then select the service again. Nothing was changed.', 'Modules.Simpay.Admin', [
                    '%version%' => (string) $e->getDetails(),
                ]),
                default => $e->getMessage(),
            };
        }

        return $this->trans('Could not connect to SimPay: %error%', 'Modules.Simpay.Admin', [
            '%error%' => $e instanceof ApiException && $e->getApiMessage() ? $e->getApiMessage() : $e->getMessage(),
        ]);
    }
}
