<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Controller;

use Configuration;
use PrestaShop\PrestaShop\Core\Form\Handler;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;
use SimPaypl\PrestaShop\Helper\SimPayChannelCache;

final class SimpayConfigurationAdminController extends FrameworkBundleAdminController
{
    private Handler $simpayFormDataHandler;
    private SimPayChannelCache $simPayChannelCache;

    public function __construct(Handler $simpayFormDataHandler, SimPayChannelCache $simPayChannelCache)
    {
        $this->simpayFormDataHandler = $simpayFormDataHandler;
        $this->simPayChannelCache = $simPayChannelCache;
    }

    public function configureAction(Request $request): Response
    {
        $configurationForm = $this->simpayFormDataHandler->getForm();
        $configurationForm->handleRequest($request);

        if ($configurationForm->isSubmitted() && $configurationForm->isValid()) {
            /** @var array<string, string> $data */
            $data = $configurationForm->getData();
            $errors = $this->simpayFormDataHandler->save($data);

            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Successful update','Admin.Notifications.Success'));

                return $this->redirectToRoute('simpay_configuration');
            }

            $this->flashErrors($errors);
        }

        return $this->render('@Modules/simpay/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $configurationForm->createView(),
            'selectedMethodsMain' => $this->getActiveSelectedChannelsByConfig(SimpayDataConfiguration::PAYMENT_METHODS_LIST_IN_MAIN),
            'availableMethodsMain' => $this->getActiveAvailableChannelsByConfig(SimpayDataConfiguration::PAYMENT_METHODS_LIST_IN_MAIN),
            'selectedMethodsSeparate' => $this->getActiveSelectedChannelsByConfig(SimpayDataConfiguration::SEPARATE_PAYMENT_METHODS_LIST, false, true),
            'availableMethodsSeparate' => $this->getActiveAvailableChannelsByConfig(SimpayDataConfiguration::SEPARATE_PAYMENT_METHODS_LIST, false, true),
        ]);
    }

    public function channelsAction(Request $request): JsonResponse
    {
        if (!$this->getContext()->employee || !$this->getContext()->employee->isLoggedBack()) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $force = (bool) $request->query->getInt('force', 0);

        return $this->json([
            'success' => true,
            'data' => [
                'available_main' => $this->getActiveAvailableChannelsByConfig(SimpayDataConfiguration::PAYMENT_METHODS_LIST_IN_MAIN, $force),
                'selected_main'  => $this->getActiveSelectedChannelsByConfig(SimpayDataConfiguration::PAYMENT_METHODS_LIST_IN_MAIN, $force),
                'available_separate' => $this->getActiveAvailableChannelsByConfig(SimpayDataConfiguration::SEPARATE_PAYMENT_METHODS_LIST, $force, true),
                'selected_separate'  => $this->getActiveSelectedChannelsByConfig(SimpayDataConfiguration::SEPARATE_PAYMENT_METHODS_LIST, $force, true),
            ],
        ]);
    }

    private function getActiveSelectedChannelsByConfig(string $configurationKey, bool $force = false, bool $excludeTransfers = false): array
    {
        // selected map from config: id => name
        $raw = (string) Configuration::get($configurationKey);
        $selected = $raw !== '' ? json_decode($raw, true) : [];

        if (!is_array($selected) || empty($selected)) {
            return [];
        }

        // channels from cache (optionally force refresh)
        $cachedChannels = $this->simPayChannelCache->get($force);

        $activeIds = [];
        foreach ($cachedChannels as $ch) {
            if ($excludeTransfers && !$this->isAllowedSeparateChannel($ch)) {
                continue;
            }
            if (!empty($ch['id'])) {
                $activeIds[(string) $ch['id']] = true;
            }
        }

        // keep only selected that still exist in cache
        $filtered = [];
        foreach ($selected as $id => $name) {
            if (isset($activeIds[(string) $id])) {
                $filtered[(string) $id] = (string) $name;
            }
        }

        return $filtered; // id => name
    }

    private function getActiveAvailableChannelsByConfig(string $configurationKey, bool $force = false, bool $excludeTransfers = false): array
    {
        $selected = $this->getActiveSelectedChannelsByConfig($configurationKey, $force, $excludeTransfers);
        $selectedIds = array_keys($selected);

        $cachedChannels = $this->simPayChannelCache->get($force);

        return array_values(array_filter($cachedChannels, function (array $ch) use ($selectedIds, $excludeTransfers) {
            if ($excludeTransfers && !$this->isAllowedSeparateChannel($ch)) {
                return false;
            }

            $id = $ch['id'] ?? null;
            return $id && !in_array((string) $id, $selectedIds, true);
        }));
    }

    private function isAllowedSeparateChannel(array $channel): bool
    {
        return (($channel['id'] ?? '') !== 'transfer') && (($channel['type'] ?? '') !== 'transfer');
    }

}
