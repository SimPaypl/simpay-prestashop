<?php

declare(strict_types=1);

use SimPaypl\PrestaShop\Form\SimpayDataConfiguration;

final class SimpayFailedModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    public function initContent(): void
    {
        parent::initContent();
        $this->context->smarty->assign([
            'repayment_enabled' => (bool) Configuration::get(SimpayDataConfiguration::REPAYMENT_ENABLED),
        ]);
        $this->setTemplate('module:simpay/views/templates/front/failed.tpl');
    }
}
