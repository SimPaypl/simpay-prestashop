<?php

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $hooks = [
        'displayBackOfficeHeader',
        'displayHeader',
        'displayOrderDetail',
        'actionGetExtraMailTemplateVars',
        'displayAdminOrderMain',
    ];

    foreach ($hooks as $hook) {
        // Avoid duplicates / errors
        if (!$module->isRegisteredInHook($hook)) {
            if (!$module->registerHook($hook)) {
                return false;
            }
        }
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
                    return false;
                }
            }
        }
    }

    if (method_exists($module, 'ensureMailTemplate')) {
        if (!$module->ensureMailTemplate('simpay_retry_payment')) {
            return false;
        }
    }

    if (method_exists($module, 'installPaymentAttemptTable')) {
        if (!$module->installPaymentAttemptTable()) {
            return false;
        }
    }
    if (method_exists($module, 'installPaymentLogTable')) {
        if (!$module->installPaymentLogTable()) {
            return false;
        }
    }

    return true;
}