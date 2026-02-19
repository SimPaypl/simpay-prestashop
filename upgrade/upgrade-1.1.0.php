<?php

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $module = Module::getInstanceByName('simpay');
    if (!$module) {
        PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot load module instance', 3);
        return false;
    }

    $moduleId = (int) Module::getModuleIdByName('simpay');
    if ($moduleId <= 0) {
        PrestaShopLogger::addLog('[simpay][upgrade] FAIL: module id not found in DB', 3);
        return false;
    }

    if (method_exists($module, 'ensureMailTemplate')) {
        if (!$module->ensureMailTemplate('simpay_retry_payment')) {
            PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install mail template', 3);
            return false;
        }
    }

    if (method_exists($module, 'installPaymentAttemptTable')) {
        if (!$module->installPaymentAttemptTable()) {
            PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install payment attempt table', 3);
            return false;
        }
    }
    if (method_exists($module, 'installPaymentLogTable')) {
        if (!$module->installPaymentLogTable()) {
            PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install payment log table', 3);
            return false;
        }
    }

    $hooks = [
        'displayBackOfficeHeader',
        'displayHeader',
        'displayOrderDetail',
        'actionGetExtraMailTemplateVars',
        'displayAdminOrderMain',
    ];

    if (!$module->registerHook($hooks)) {
        PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install hooks', 3);
        return false;
    }

    $key = SimpayDataConfiguration::REPAYMENT_ENABLED;
    if (Configuration::get($key) === false) {
        Configuration::updateValue($key, true);
    }

    if (method_exists($module, 'ensureOrderState')) {
        if (!$module->ensureOrderState($module::CONFIG_OS_AWAITING, [
            'en' => 'Awaiting SimPay payment',
            'pl' => 'Oczekuje na płatność SimPay',
        ], '#03d14e', true)) {
            PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install os awaiting state', 3);
            return false;
        }

        if (!$module->ensureOrderState(
            $module::CONFIG_OS_EXPIRED,
            [
                'en' => 'SimPay payment expired',
                'pl' => 'Płatność SimPay wygasła',
            ],
            '#03d14e',
            true,
            false,
            false,
            false,
            false,
            false,
            false,
            true,
            'simpay_retry_payment'
        )) {
            PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install os expired state', 3);
            return false;
        }

        // Update existing AWAITING color/name if it already exists (ensureOrderState doesn't update)
        $awaitingId = (int) Configuration::get($module::CONFIG_OS_AWAITING);
        if ($awaitingId > 0) {
            $os = new OrderState($awaitingId);
            if (Validate::isLoadedObject($os) && $os->module_name === $module->name) {
                $os->color = '#03d14e';
                // Optional: update translations (safe)
                foreach (Language::getLanguages(false) as $lang) {
                    $iso = Tools::strtolower((string) $lang['iso_code']);
                    $os->name[(int)$lang['id_lang']] = $iso === 'pl'
                        ? 'Oczekuje na płatność SimPay'
                        : 'Awaiting SimPay payment';
                }
                if (!$os->save()) {
                    PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot update os awaiting state', 3);
                    return false;
                }
            }
        }
    }

    return true;
}