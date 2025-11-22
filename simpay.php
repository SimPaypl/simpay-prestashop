<?php

declare(strict_types=1);

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShopBundle\Entity\Repository\TabRepository;
use PrestaShopBundle\Service\Routing\Router;
use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

final class Simpay extends PaymentModule
{
    public const CONFIG_OS_AWAITING = 'PS_OS_SIMPAY_AWAITING';

    private const MODULE_ADMIN_CONTROLLERS = [
        [
            'class_name' => 'SimpayConfigurationAdminParentController',
            'name' => 'SimPay Module',
            'parent_class_name' => 'AdminParentModulesSf',
            'visible' => false,
        ],
        [
            'class_name' => 'SimpayConfigurationAdminController',
            'route_name' => 'simpay_configuration',
            'name' => 'Configuration',
            'parent_class_name' => 'SimpayConfigurationAdminParentController',
            'visible' => false,
        ],
    ];

    private const HOOKS = [
        'paymentOptions',
    ];

    public function __construct()
    {
        $this->name = 'simpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Payments Solution Sp. z o.o.';
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->controllers = ['failed', 'notify', 'validate'];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Płatności SimPay', [], 'Modules.Simpay.Admin');
        $this->description = $this->trans('Połącz swój sklep z płatnościami SimPay', [], 'Modules.Simpay.Admin');
        $this->confirmUninstall = $this->trans('Czy na pewno chcesz odinstalować wtyczkę?', [], 'Modules.Simpay.Admin');
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registerHook(self::HOOKS)) {
            return false;
        }

        if (!$this->createOrderState(
            self::CONFIG_OS_AWAITING,
            [
                'en' => 'Awaiting SimPay payment',
                'pl' => 'Oczekuje na płatność SimPay',
            ],
            '#34209e',
            true,
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            'awaiting_simpay_payment'
        )) {
            return false;
        }

        if (!$this->installTabs()) {
            return false;
        }

        return true;
    }

    public function uninstall(): bool
    {
        if (!parent::uninstall()) {
            return false;
        }

        if (!$this->deleteOrderState()) {
            return false;
        }

        if (!$this->uninstallTabs()) {
            return false;
        }

        return true;
    }

    /**
     * @param array{cart: Cart} $params
     * @return array<PaymentOption>
     */
    public function hookPaymentOptions(array $params): array
    {
        $cart = $params['cart'];

        if (false === Validate::isLoadedObject($cart)) {
            return [];
        }

        if (false === $this->checkCurrency($cart)) {
            return [];
        }

        if ($cart->isVirtualCart()) {
            return [];
        }

        if (!isset($this->context->link)) {
            return [];
        }

        $methods = [];

        $hasBlik = (bool)Configuration::get(SimpayDataConfiguration::SHOW_BLIK_SEPARATELY);
        $hasBlikBnpl = (bool)Configuration::get(SimpayDataConfiguration::SHOW_BLIK_BNPL_SEPARATELY);
        $hasPayPo = (bool)Configuration::get(SimpayDataConfiguration::SHOW_PAYPO_SEPARATELY);

        if($hasBlik) {
            $methods[] = (new PaymentOption())
                ->setModuleName($this->name)
                ->setCallToActionText('Płatność BLIK')
                ->setAction($this->context->link->getModuleLink((string)$this->name, 'validate', ['method'=>'blik'], true))
                ->setInputs([
                    'token' => [
                        'name' => 'token',
                        'type' => 'hidden',
                        'value' => Tools::getToken('simpay'),
                    ],
                ])
                ->setAdditionalInformation('')
                ->setLogo('https://cdn.simpay.pl/ecommerce/payment_providers/blik.png');
        }
        if($hasBlikBnpl) {
            $methods[] = (new PaymentOption())
                ->setModuleName($this->name)
                ->setCallToActionText('Blik Płacę Później')
                ->setAction($this->context->link->getModuleLink((string)$this->name, 'validate', ['method'=>'blik-paylater'], true))
                ->setInputs([
                    'token' => [
                        'name' => 'token',
                        'type' => 'hidden',
                        'value' => Tools::getToken('simpay'),
                    ],
                ])
                ->setAdditionalInformation('Dowiedz się więcej na <a href="https://www.blik.com/place-pozniej" target="_blank">https://www.blik.com/place-pozniej</a>')
                ->setLogo('https://cdn.simpay.pl/ecommerce/payment_providers/blik_paylater.png');
        }
        if($hasPayPo) {
            $methods[] = (new PaymentOption())
                ->setModuleName($this->name)
                ->setCallToActionText('PayPo: Kup teraz, zapłać później')
                ->setAction($this->context->link->getModuleLink((string)$this->name, 'validate', ['method'=>'paypo'], true))
                ->setInputs([
                    'token' => [
                        'name' => 'token',
                        'type' => 'hidden',
                        'value' => Tools::getToken('simpay'),
                    ],
                ])
                ->setAdditionalInformation('Dowiedz się więcej na <a href="https://start.paypo.pl/" target="_blank">https://start.paypo.pl</a>')
                ->setLogo('https://cdn.simpay.pl/ecommerce/payment_providers/paypo.png');
        }

        $methods[] = (new PaymentOption())
            ->setModuleName($this->name)
            ->setCallToActionText('Płatność online SimPay')
            ->setAction($this->context->link->getModuleLink((string)$this->name, 'validate', [], true))
            ->setInputs([
                'token' => [
                    'name' => 'token',
                    'type' => 'hidden',
                    'value' => Tools::getToken('simpay'),
                ],
            ])
            ->setLogo('https://cdn.simpay.pl/ecommerce/payment_providers/simpay.png');

        return $methods;
    }

    public function getContent(): void
    {
        /** @var Router $router */
        $route = $this->get('router')->generate('simpay_configuration');
        ToolsCore::redirectAdmin($route);
    }

    private function installTabs(): bool
    {
        $tabRepository = $this->get('prestashop.core.admin.tab.repository');
        /** @var TabRepository $tabRepository */

        foreach (self::MODULE_ADMIN_CONTROLLERS as $controller) {
            if ($tabRepository->findOneIdByClassName($controller['class_name'])) {
                continue;
            }

            $tab = new Tab();
            $tab->class_name = $controller['class_name'];
            $tab->active = $controller['visible'];

            /** @var array<string,array{
             *     id_lang: int,
             *     name: string,
             *     active: int,
             *     iso_code: string,
             *     language_code: string,
             *     locale: string,
             *     date_format_lite: string,
             *     date_format_full: string,
             *     is_rtl: int,
             *     id_shop: int,
             *     id_shop_list: array<bool>,
             * }> $languages
             */
            $languages = Language::getLanguages(false);
            foreach ($languages as $lang) {
                $tab->name[$lang['id_lang']] = $this->trans($controller['name'], [], 'Modules.Simpay.Admin', $lang['locale']);
            }
            $tab->id_parent = $tabRepository->findOneIdByClassName($controller['parent_class_name']);
            $tab->module = 'simpay';
            if (!$tab->add()) {
                return false;
            }
        }

        return true;
    }

    private function uninstallTabs(): bool
    {
        $tabRepository = $this->get('prestashop.core.admin.tab.repository');
        /** @var TabRepository $tabRepository */

        foreach (self::MODULE_ADMIN_CONTROLLERS as $controller) {
            $id_tab = (int)$tabRepository->findOneIdByClassName($controller['class_name']);
            $tab = new Tab($id_tab);
            if (Validate::isLoadedObject($tab)) {
                if (!$tab->delete()) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, string> $nameByLangIsoCode */
    private function createOrderState(
        string $configurationKey,
        array  $nameByLangIsoCode,
        string $color,
        bool   $isLogable = false,
        bool   $isPaid = false,
        bool   $isInvoice = false,
        bool   $isShipped = false,
        bool   $isDelivery = false,
        bool   $isPdfDelivery = false,
        bool   $isPdfInvoice = false,
        bool   $isSendEmail = false,
        string $template = '',
        bool   $isHidden = false,
        bool   $isUnremovable = true,
        bool   $isDeleted = false,
    ): bool
    {
        $tabNameByLangId = [];

        foreach ($nameByLangIsoCode as $langIsoCode => $name) {
            /** @var array<string, array<string>> $languages */
            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                if (Tools::strtolower($language['iso_code']) === $langIsoCode) {
                    $tabNameByLangId[(int)$language['id_lang']] = $name;
                } elseif (isset($nameByLangIsoCode['en'])) {
                    $tabNameByLangId[(int)$language['id_lang']] = $nameByLangIsoCode['en'];
                }
            }
        }

        $orderState = new OrderState();
        $orderState->module_name = $this->name;
        $orderState->name = $tabNameByLangId;
        $orderState->color = $color;
        $orderState->logable = $isLogable;
        $orderState->paid = $isPaid;
        $orderState->invoice = $isInvoice;
        $orderState->shipped = $isShipped;
        $orderState->delivery = $isDelivery;
        $orderState->pdf_delivery = $isPdfDelivery;
        $orderState->pdf_invoice = $isPdfInvoice;
        $orderState->send_email = $isSendEmail;
        $orderState->hidden = $isHidden;
        $orderState->unremovable = $isUnremovable;
        $orderState->template = $template;
        $orderState->deleted = $isDeleted;

        if (false === $orderState->add()) {
            $this->_errors[] = sprintf(
                'Failed to create OrderState %s',
                $configurationKey
            );

            return false;
        }

        if (false === Configuration::updateGlobalValue($configurationKey, (int)$orderState->id)) {
            $this->_errors[] = sprintf(
                'Failed to save OrderState %s to Configuration',
                $configurationKey
            );

            return false;
        }

        $orderStateImgPath = $this->getLocalPath() . 'views/img/orderstate/' . $configurationKey . '.png';
        if (false === Tools::file_exists_cache($orderStateImgPath)) {
            $this->_errors[] = sprintf(
                'Failed to find icon file of OrderState %s',
                $configurationKey
            );

            return false;
        }

        if (false === Tools::copy($orderStateImgPath, _PS_ORDER_STATE_IMG_DIR_ . $orderState->id . '.gif')) {
            $this->_errors[] = sprintf(
                'Failed to copy icon of OrderState %s',
                $configurationKey
            );

            return false;
        }

        return true;
    }

    private function deleteOrderState(): bool
    {
        $result = true;

        $orderStateCollection = new PrestaShopCollection('OrderState');
        $orderStateCollection->where('module_name', '=', $this->name);
        /** @var OrderState[] $orderStates */
        $orderStates = $orderStateCollection->getAll();

        foreach ($orderStates as $orderState) {
            $orderState->deleted = true;
            if (!$orderState->save()) {
                $result = false;
            }
        }

        return $result;
    }

    private function checkCurrency(Cart $cart): bool
    {
        $currencyOrder = new Currency($cart->id_currency);

        return 'PLN' === $currencyOrder->iso_code;
    }
}
