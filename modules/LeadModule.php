<?php

namespace modules;

use yii\base\Module as BaseModule;

class LeadModule extends BaseModule
{
    public function init(): void
    {
        $this->controllerNamespace = 'modules\\controllers';
        parent::init();
    }
}
