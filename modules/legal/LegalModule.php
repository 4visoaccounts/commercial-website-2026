<?php

namespace modules\legal;

use Craft;
use modules\legal\console\controllers\LegalController;
use yii\base\Module as BaseModule;

/**
 * Legal pages module.
 *
 * Console commands:
 *   php craft legal/prepare — frees /privacy and /terms-and-conditions from any other entry (run on prod before project-config/apply)
 *   php craft legal/setup   — creates the legal page fields, entry types and singles (writes project config; local only)
 *   php craft legal/import  — (re)imports seeds/legal/*.json into the Privacy Policy / Terms singles
 */
class LegalModule extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/legal', __DIR__);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\legal\\console\\controllers';
            // Expose `php craft legal/setup` and `php craft legal/import` instead of `legal/legal/<action>`
            $this->controllerMap = [
                'prepare' => ['class' => LegalController::class, 'defaultAction' => 'prepare'],
                'setup' => ['class' => LegalController::class, 'defaultAction' => 'setup'],
                'import' => ['class' => LegalController::class, 'defaultAction' => 'import'],
            ];
        } else {
            $this->controllerNamespace = 'modules\\legal\\controllers';
        }

        parent::init();
    }
}
