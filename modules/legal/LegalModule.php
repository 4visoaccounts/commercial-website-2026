<?php

namespace modules\legal;

use Craft;
use yii\base\Module as BaseModule;

/**
 * Legal pages module.
 *
 * Console commands:
 *   php craft legal/setup   — creates the legal page fields, entry types and singles (writes project config)
 *   php craft legal/import  — (re)imports seeds/legal/*.json into the Privacy Policy / Terms singles
 */
class LegalModule extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/legal', __DIR__);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\legal\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\legal\\controllers';
        }

        parent::init();
    }
}
