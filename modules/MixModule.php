<?php

namespace modules;

use Craft;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use yii\base\Module as BaseModule;

/**
 * Twig function mix('/css/app.css') → '/css/app.css?id=<hash>' from web/mix-manifest.json,
 * so browsers fetch a new file after every production build (cache busting).
 * Falls back to the plain path if the manifest or entry is missing.
 */
class MixModule extends BaseModule
{
    public function init()
    {
        parent::init();

        Craft::$app->onInit(function() {
            Craft::$app->getView()->registerTwigExtension(new class extends AbstractExtension {
                private ?array $manifest = null;

                public function getFunctions(): array
                {
                    return [new TwigFunction('mix', [$this, 'mix'])];
                }

                public function mix(string $path): string
                {
                    if ($this->manifest === null) {
                        $file = Craft::getAlias('@webroot') . '/mix-manifest.json';
                        $this->manifest = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
                    }

                    return $this->manifest[$path] ?? $path;
                }
            });
        });
    }
}
