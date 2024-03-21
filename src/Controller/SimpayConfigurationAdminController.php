<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Controller;

use PrestaShop\PrestaShop\Core\Form\Handler;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SimpayConfigurationAdminController extends FrameworkBundleAdminController
{
    private Handler $simpayFormDataHandler;
    public function __construct(Handler $simpayFormDataHandler)
    {
        $this->simpayFormDataHandler = $simpayFormDataHandler;
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
                $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));

                return $this->redirectToRoute('simpay_configuration');
            }

            $this->flashErrors($errors);
        }

        return $this->render('@Modules/simpay/views/templates/admin/configuration.html.twig', [
            'configurationForm' => $configurationForm->createView(),
        ]);
    }
}
