<?php

declare(strict_types=1);

namespace SimPaypl\PrestaShop\Form;

use Link;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class SimpayFormType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('service_ipn_notify_url', UrlType::class, [
                'label' => $this->trans('IPN URL address', 'Modules.Simpay.Admin'),
                'help' => $this->trans(
                    'Online payments → Services → Details → Settings → IPN URL address',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
                'mapped' => false,
                'disabled' => true,
                'data' => (new Link())->getModuleLink('simpay', 'notify', [], true),
            ])
            ->add('api_password', TextType::class, [
                'label' => $this->trans('API password', 'Modules.Simpay.Admin'),
                'help' => $this->trans(
                    'Customer account → API → Details → Password / Bearer Token',
                    'Modules.Simpay.Admin'
                ),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('service_id', TextType::class, [
                'label' => $this->trans('Service ID', 'Modules.Simpay.Admin'),
                'help' => $this->trans(
                    'Online payments → Services → Details → Service ID',
                    'Modules.Simpay.Admin'
                ),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('service_ipn_signature_key', TextType::class, [
                'label' => $this->trans('IPN signature key', 'Modules.Simpay.Admin'),
                'help' => $this->trans(
                    'Online payments → Services → Details → Settings → IPN signature key',
                    'Modules.Simpay.Admin'
                ),
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('ipn_check_ip', SwitchType::class, [
                'label' => $this->trans(
                    'Verify incoming IP address in IPN notifications',
                    'Modules.Simpay.Admin'
                ),
                'help' => $this->trans(
                    'If your shop is using Cloudflare, we do not recommend enabling this option.',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
            ])
            ->add('show_payment_methods_in_main', SwitchType::class, [
                'label' => $this->trans(
                    'Show payment methods inside the SimPay payment',
                    'Modules.Simpay.Admin'
                ),
                'help' => $this->trans(
                    'When enabled, customers will see the list of available payment methods after selecting SimPay. The order of methods is taken from settings below.',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
            ])
            ->add('payment_methods_list_in_main', HiddenType::class, [
                'label' => $this->trans(
                    'List of payment methods inside the SimPay payment',
                    'Modules.Simpay.Admin'
                )
            ])
            ->add('show_separate_payment_methods', SwitchType::class, [
                'label' => $this->trans(
                    'Show selected payment methods as separate payment options',
                    'Modules.Simpay.Admin'
                ),
                'help' => $this->trans(
                    'When enabled, selected methods will be displayed as additional standalone payment options in checkout.',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
            ])
            ->add('separate_payment_methods_list', HiddenType::class, [
                'label' => $this->trans(
                    'List of payment methods shown as separate options',
                    'Modules.Simpay.Admin'
                )
            ])
            ->add('show_blik_in_widget', SwitchType::class, [
                'label' => $this->trans(
                    'Show BLIK as widget',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
            ])
            ->add('blik_oneclick_enabled', SwitchType::class, [
                'label' => $this->trans(
                    'Enable BLIK OneClick (payment without code)',
                    'Modules.Simpay.Admin'
                ),
                'help' => $this->trans(
                    'Allows returning customers to pay with one click, without entering a BLIK code. Requires BLIK Level 0 and OneClick to be activated in your SimPay panel.',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
            ])
            ->add('repayment_enabled', SwitchType::class, [
                'label' => $this->trans(
                    'Re-payment enabled',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
            ])
            ->add('commission_mode', ChoiceType::class, [
                'label' => $this->trans(
                    'Commission paid by',
                    'Modules.Simpay.Admin'
                ),
                'help' => $this->trans(
                    'Choose who pays the transaction commission — the merchant, the payer, or split between both.',
                    'Modules.Simpay.Admin'
                ),
                'choices' => [
                    $this->trans('Merchant', 'Modules.Simpay.Admin') => 'merchant',
                    $this->trans('Payer', 'Modules.Simpay.Admin') => 'payer',
                    $this->trans('Split', 'Modules.Simpay.Admin') => 'split',
                ],
                'required' => true,
            ])
            ->add('commission_split', IntegerType::class, [
                'label' => $this->trans(
                    'Payer commission share (%)',
                    'Modules.Simpay.Admin'
                ),
                'help' => $this->trans(
                    'Percentage of the commission covered by the payer. E.g. 50 = payer pays half, merchant pays the other half.',
                    'Modules.Simpay.Admin'
                ),
                'required' => false,
                'constraints' => [
                    new Range(['min' => 1, 'max' => 99]),
                ],
                'attr' => [
                    'min' => 1,
                    'max' => 99,
                ],
            ]);
    }

}
