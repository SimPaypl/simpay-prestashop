<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_1($module)
{
    $module = Module::getInstanceByName('simpay');
    if (!$module) {
        PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot load module instance', 3);
        return false;
    }

    if (method_exists($module, 'installBlikAliasTable')) {
        if (!$module->installBlikAliasTable()) {
            PrestaShopLogger::addLog('[simpay][upgrade] FAIL: cannot install blik alias table', 3);
            return false;
        }
    }

    return true;
}

