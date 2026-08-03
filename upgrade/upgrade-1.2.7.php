<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param Simpay $object
 * @return bool
 */
function upgrade_module_1_2_7($object)
{
    $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'simpay_payment_attempt` ADD COLUMN `commission_mode` VARCHAR(16) DEFAULT NULL AFTER `is_active`';

    try {
        Db::getInstance()->execute($sql);
    } catch (\Throwable $e) {
        // Column may already exist
    }

    return true;
}

