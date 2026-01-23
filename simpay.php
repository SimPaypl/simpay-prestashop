<?php

declare(strict_types=1);

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
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

    private const HOOKS = [
        'paymentOptions',
        'displayBackOfficeHeader',
        'displayHeader'
    ];

    public function __construct()
    {
        $this->name = 'simpay';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.0';
        $this->author = 'Payments Solution Sp. z o.o.';
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
        $this->controllers = ['failed', 'notify', 'validate'];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('SimPay Payments', [], 'Modules.Simpay.Admin');
        $this->description = $this->trans('Accept fast and secure online payments with SimPay – BLIK, online transfers and instant payments. Easy integration and smooth checkout for your customers.', [], 'Modules.Simpay.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall? You will lose all your settings!', [], 'Modules.Simpay.Admin');
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

        return true;
    }

    /**
     * Indicates that this module uses the new PrestaShop translation system.
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Module header register scripts and styles.
     *
     * @return void
     */
    public function hookDisplayHeader(): void
    {
        if (
            !isset($this->context->controller) ||
            !in_array($this->context->controller->php_self, ['order', 'order-opc'], true)
        ) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/front/simpay.css');
        $this->context->controller->addJS($this->_path . 'views/js/front/front.js');
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('controller') !== 'SimpayConfigurationAdminController') {
            return;
        }

        Media::addJsDef([
            'simpayChannelsUrl' => $this->get('router')->generate('simpay_admin_channels'),
        ]);

        $this->context->controller->addJS($this->_path . 'views/js/admin/payment-methods.js');
        $this->context->controller->addCSS($this->_path . 'views/css/admin/admin.css');
    }

    /**
     * @param array{cart: Cart} $params
     * @return array<PaymentOption>
     */
    public function hookPaymentOptions(array $params): array
    {
        if (!$this->isConfigurationComplete()) {
            return [];
        }

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

        $showPaymentMethods = (bool)Configuration::get(SimpayDataConfiguration::SHOW_PAYMENT_METHODS_IN_MAIN);
        $hasBlik = (bool)Configuration::get(SimpayDataConfiguration::SHOW_BLIK_SEPARATELY);
        $hasBlikBnpl = (bool)Configuration::get(SimpayDataConfiguration::SHOW_BLIK_BNPL_SEPARATELY);
        $hasPayPo = (bool)Configuration::get(SimpayDataConfiguration::SHOW_PAYPO_SEPARATELY);

        if($hasBlik) {
            $methods[] = (new PaymentOption())
                ->setModuleName($this->name)
                ->setCallToActionText($this->trans('Pay with BLIK', [], 'Modules.Simpay.Shop'))
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
                ->setCallToActionText($this->trans('BLIK Pay Later', [], 'Modules.Simpay.Shop'))
                ->setAction($this->context->link->getModuleLink((string)$this->name, 'validate', ['method'=>'blik-paylater'], true))
                ->setInputs([
                    'token' => [
                        'name' => 'token',
                        'type' => 'hidden',
                        'value' => Tools::getToken('simpay'),
                    ],
                ])
                ->setAdditionalInformation($this->trans('Learn more at <a href=":url" target="_blank">:url</a>', ['url' => 'https://www.blik.com/place-pozniej'], 'Modules.Simpay.Shop'))
                ->setLogo('https://cdn.simpay.pl/ecommerce/payment_providers/blik_paylater.png');
        }
        if($hasPayPo) {
            $methods[] = (new PaymentOption())
                ->setModuleName($this->name)
                ->setCallToActionText($this->trans('PayPo – Buy now, pay later', [], 'Modules.Simpay.Shop'))
                ->setAction($this->context->link->getModuleLink((string)$this->name, 'validate', ['method'=>'paypo'], true))
                ->setInputs([
                    'token' => [
                        'name' => 'token',
                        'type' => 'hidden',
                        'value' => Tools::getToken('simpay'),
                    ],
                ])
                ->setAdditionalInformation($this->trans('Learn more at <a href=":url" target="_blank">paypo.pl</a>', ['url' => 'https://start.paypo.pl/'], 'Modules.Simpay.Shop'))
                ->setLogo('https://cdn.simpay.pl/ecommerce/payment_providers/paypo.png');
        }

        $this->context->smarty->assign([
            'simpay_methods' => $this->getCheckoutMethodsGridToDisplay(),
            'simpay_module_name' => $this->name,
            'simpay_action' => $this->context->link->getModuleLink($this->name, 'validate', [], true),
            'simpay_token' => Tools::getToken('simpay'),
            'simpay_show_methods' => $showPaymentMethods
        ]);

        $gridHtml = $this->fetch('module:' . $this->name . '/views/templates/hook/payment_grid.tpl');

        $methods[] = (new PaymentOption())
            ->setModuleName($this->name)
            ->setCallToActionText($this->trans('SimPay online payment', [], 'Modules.Simpay.Shop'))
            ->setAction($this->context->link->getModuleLink((string)$this->name, 'validate', [], true))
            ->setForm($gridHtml)
            ->setLogo('https://cdn.simpay.pl/ecommerce/payment_providers/simpay.png');

        return $methods;
    }

    private function isConfigurationComplete(): bool
    {
        return
            (bool) Configuration::get(SimpayDataConfiguration::API_PASSWORD)
            && (bool) Configuration::get(SimpayDataConfiguration::SERVICE_ID)
            && (bool) Configuration::get(SimpayDataConfiguration::SERVICE_IPN_SIGNATURE_KEY);
    }


    /**
     * Build a safe list of methods to display in checkout
     * @return array<int, array{id:string,name:string,type:string,img:?string}>
     */
    private function getCheckoutMethodsGridToDisplay(): array
    {
        // Selected in admin
        $raw = (string) Configuration::get('SIMPAY_PAYMENT_METHODS_LIST_IN_MAIN');
        $selected = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($selected)) {
            $selected = [];
        }

        $selectedIds = array_keys($selected);

        // Available from cache
        /** @var \SimPaypl\PrestaShop\Helper\SimPayChannelCache $cache */
        $cache = $this->get('prestashop.module.simpay.channel_cache');
        $available = $cache->get();

        if (empty($available)) {
            return [];
        }

        $availableById = [];
        foreach ($available as $ch) {
            if (!isset($ch['id'])) {
                continue;
            }
            $availableById[(string) $ch['id']] = $ch;
        }

        // keep admin order
        $methods = [];
        foreach ($selectedIds as $id) {
            if (isset($availableById[$id])) {
                $methods[] = $availableById[$id];
            }
        }

        // if admin selected nothing or all missing, show some defaults
        if (empty($methods)) {
            $methods = array_slice($available, 0, 12);
        }

        return $methods;
    }


    public function getContent(): void
    {
        /** @var Router $router */
        $route = $this->get('router')->generate('simpay_configuration');
        ToolsCore::redirectAdmin($route);
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
