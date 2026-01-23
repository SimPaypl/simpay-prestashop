<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Controller;

use Configuration;
use PrestaShop\PrestaShop\Core\Form\Handler;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
            'selectedMethods' => $this->getActiveSelectedChannels(),
            'availableMethods' => $this->getActiveAvailableChannels(),
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
                'available' => $this->getActiveAvailableChannels($force), // [{id,name,type,img}]
                'selected'  => $this->getActiveSelectedChannels($force),  // map id=>name
            ],
        ]);
    }

    private function getActiveSelectedChannels(bool $force = false): array
    {
        // selected map from config: id => name
        $raw = (string) Configuration::get('SIMPAY_PAYMENT_METHODS_LIST_IN_MAIN');
        $selected = $raw !== '' ? json_decode($raw, true) : [];

        if (!is_array($selected) || empty($selected)) {
            return [];
        }

        // channels from cache (optionally force refresh)
        $cachedChannels = $this->simPayChannelCache->get($force);

        $activeIds = [];
        foreach ($cachedChannels as $ch) {
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

    private function getActiveAvailableChannels(bool $force = false): array
    {
        $selected = $this->getActiveSelectedChannels($force);
        $selectedIds = array_keys($selected);

        $cachedChannels = $this->simPayChannelCache->get($force);

        return array_values(array_filter($cachedChannels, function (array $ch) use ($selectedIds) {
            $id = $ch['id'] ?? null;
            return $id && !in_array((string) $id, $selectedIds, true);
        }));
    }

}
