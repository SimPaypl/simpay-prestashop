<?php

declare(strict_types=1);

final class SimpayFailedModuleFrontController extends ModuleFrontController
{
    /** @var Simpay */
    public $module;

    public function initContent(): void
    {
        parent::initContent();
        $this->setTemplate('module:simpay/views/templates/front/failed.tpl');
    }
}
