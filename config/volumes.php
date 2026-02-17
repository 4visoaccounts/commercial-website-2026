<?php
/**
 * Volumes Configuration
 *
 * Configure asset volumes for managing uploads in the control panel.
 *
 * @see https://craftcms.com/docs/5.x/reference/config/volumes.html
 */

use craft\models\Volume;

return [
    'uploads' => [
        'name' => 'Uploads',
        'handle' => 'uploads',
        'fs' => 'uploads',
        'transformFs' => 'uploads',
        'transformSubpath' => '_transforms',
        'titleTranslationMethod' => Volume::TRANSLATION_METHOD_SITE,
    ],
];
