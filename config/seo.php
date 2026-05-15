<?php
/**
 * ether/seo plugin config.
 *
 * Auto-loaded by Craft because the filename matches the plugin handle ("seo").
 * Overrides values on \ether\seo\models\Settings.
 */

return [
    // Point the SEO hook to our custom template, which adds a content-derived
    // title/description fallback chain. See templates/_seo/meta.twig.
    'metaTemplate' => '_seo/meta',
];
