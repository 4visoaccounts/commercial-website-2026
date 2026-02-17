<?php

namespace modules;

use Craft;
use craft\helpers\App;
use yii\base\Event;
use yii\base\Module as BaseModule;
use yii\web\Application;

class ComingSoonModule extends BaseModule
{
    public function init()
    {
        parent::init();

        // Only run on web requests
        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
            Event::on(
                Application::class,
                Application::EVENT_BEFORE_REQUEST,
                [$this, 'handleComingSoonRedirect']
            );
        }
    }

    public function handleComingSoonRedirect()
    {
        // Check if coming soon mode is enabled
        if (App::env('COMING_SOON') !== true) {
            
            return;
        }


        $request = Craft::$app->getRequest();
        $pathInfo = $request->getPathInfo();

        // Allow access to CP, coming-soon page, and assets
        $allowedPaths = ['coming-soon', 'cpresources', 'assets', 'actions'];

        if ($request->getIsCpRequest()) {
            return;
        }

        foreach ($allowedPaths as $path) {
            if (str_starts_with($pathInfo, $path)) {
                return;
            }
        }

       

        // Redirect to coming soon page
        Craft::$app->getResponse()->redirect('/coming-soon')->send();
         var_dump('Redirect???');
            die();
         
        Craft::$app->end();
    }
}
