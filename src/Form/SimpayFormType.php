<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Form;

use Link;
use PrestaShop\PrestaShop\Adapter\Admin\UrlGenerator;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SimpayFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('api_key', TextType::class, [
                'label' => $this->trans('API Key', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Konto Klienta -> API -> Szczegoly -> Klucz', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('api_password', TextType::class, [
                'label' => $this->trans('API Password', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Konto Klienta -> API -> Szczegoly -> Hasło / Bearer Token ', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('service_id', TextType::class, [
                'label' => $this->trans('Service ID', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Platnosci online -> Uslugi -> Szczegoly -> ID', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('service_ipn_signature_key', TextType::class, [
                'label' => $this->trans('Service IPN Signature Key', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Platnosci online -> Uslugi -> Szczegoly -> Ustawienia -> Klucz do sygnatury IPN', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])

            ->add('service_ipn_notify_url', UrlType::class, [
                'label' => $this->trans('Service IPN Notify URL', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Platnosci online -> Uslugi -> Szczegoly -> Ustawienia -> Adres url do powiadomień IPN', 'Modules.Simpay.Admin'),
                'required' => false,
                'mapped' => false,
                'disabled' => true,
                'data' => (new Link())->getModuleLink('simpay', 'notify', [], true),
            ])
        ;
    }

}
