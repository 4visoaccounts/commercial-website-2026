<?php
/**
 * Filesystems Configuration
 *
 * Configure filesystems for storing assets and uploads.
 *
 * @see https://craftcms.com/docs/5.x/reference/config/fs.html
 */

use craft\fs\Local;

return [
    'uploads' => [
        'class' => Local::class,
        'path' => '@webroot/uploads',
        'url' => '@web/uploads',
        'hasUrls' => true,
    ],
];
