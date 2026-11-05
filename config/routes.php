<?php
/**
 * Site URL Rules
 *
 * You can define custom site URL rules here, which Craft will check in addition
 * to routes defined in Settings → Routes.
 *
 * Read about Craft's routing behavior (and this file's structure), here:
 * @link https://craftcms.com/docs/5.x/system/routing.html
 */

return [
    'stylesheet' => ['template' => 'pages/stylesheet'],
    'coming-soon' => ['template' => 'pages/coming-soon'],
    // Support ticket page, hidden for now (template kept as pages/_help.twig so Craft won't serve it by path).
    // To bring it back: rename to pages/help.twig and restore: 'help' => ['template' => 'pages/help'],
    'api' => 'graphql/api',
];
