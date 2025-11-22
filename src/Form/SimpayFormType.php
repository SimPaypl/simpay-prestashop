<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Form;

use Link;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SimpayFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('service_ipn_notify_url', UrlType::class, [
                'label' => $this->trans('Adres URL IPN (USTAW GO W PANELU SIMPAY)', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Płatności online -> Usługi -> Szczegóły -> Ustawienia -> Adres URL IPN', 'Modules.Simpay.Admin'),
                'required' => false,
                'mapped' => false,
                'disabled' => true,
                'data' => (new Link())->getModuleLink('simpay', 'notify', [], true),
            ])
            ->add('api_password', TextType::class, [
                'label' => $this->trans('Hasło API', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Konto Klienta -> API -> Szczegóły -> Hasło / Bearer Token ', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('service_id', TextType::class, [
                'label' => $this->trans('ID Usługi', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Płatności online -> Usługi -> Szczegóły -> ID usługi', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('service_ipn_signature_key', TextType::class, [
                'label' => $this->trans('Klucz do sygnatury IPN', 'Modules.Simpay.Admin'),
                'help' => $this->trans('Płatności online -> Usługi -> Szczegóły -> Ustawienia -> Klucz do sygnatury IPN', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('ipn_check_ip', ChoiceType::class, [
                'label' => $this->trans('Weryfikuj adres IP przychodzący w powiadomieniach IPN', 'Modules.Simpay.Admin'),
                'help' => 'Jeśli Twój sklep stoi za CloudFlare to nie rekomendujemy włączania tego parametru.',
                'constraints' => [
                    new NotBlank(),
                ],
                'choices' => [
                    $this->trans('Nie', 'Modules.Simpay.Admin') => '0',
                    $this->trans('Tak', 'Modules.Simpay.Admin') => '1',
                ],
            ])
            ->add('show_blik_separately', ChoiceType::class, [
                'label' => $this->trans('Pokaż BLIK jako dodatkową metodę płatności', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
                'choices' => [
                    $this->trans('Nie', 'Modules.Simpay.Admin') => '0',
                    $this->trans('Tak', 'Modules.Simpay.Admin') => '1',
                ],
            ])
            ->add('show_blik_bnpl_separately', ChoiceType::class, [
                'label' => $this->trans('Pokaż BLIK Płacę Później jako dodatkową metodę płatności', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
                'choices' => [
                    $this->trans('Nie', 'Modules.Simpay.Admin') => '0',
                    $this->trans('Tak', 'Modules.Simpay.Admin') => '1',
                ],
            ])
            ->add('show_paypo_separately', ChoiceType::class, [
                'label' => $this->trans('Pokaż PayPo jako dodatkową metodę płatności', 'Modules.Simpay.Admin'),
                'constraints' => [
                    new NotBlank(),
                ],
                'choices' => [
                    $this->trans('Nie', 'Modules.Simpay.Admin') => '0',
                    $this->trans('Tak', 'Modules.Simpay.Admin') => '1',
                ],
            ]);
    }

}
