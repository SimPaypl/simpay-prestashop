<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.3.0: "Connect with SimPay" (OAuth) - new admin routes and controller service
 * require a fresh Symfony container / router cache.
 * Product-based refunds - new `items` column in simpay_refunds.
 *
 * @param Simpay $object
 * @return bool
 */
function upgrade_module_1_3_0($object)
{
    // Refunds are built from returned products: remember which items (and shipping) each refund covers
    try {
        Db::getInstance()->execute(
            'ALTER TABLE `' . _DB_PREFIX_ . 'simpay_refunds` ADD COLUMN `items` TEXT DEFAULT NULL AFTER `amount`'
        );
    } catch (\Throwable $e) {
        // Column may already exist
    }

    try {
        Tools::clearSf2Cache();
    } catch (\Throwable $e) {
        PrestaShopLogger::addLog('SimPay: Failed to clear Symfony cache during upgrade: ' . $e->getMessage(), 2);
    }

    return true;
}
