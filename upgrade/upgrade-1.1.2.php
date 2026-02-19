<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_2($module)
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

    if (method_exists($module, 'installRefundsTable')) {
        if (!$module->installRefundsTable()) {
            PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install refunds table', 3);
            return false;
        }
    }

    return true;
}