<?php

namespace modules;

use Craft;
use craft\elements\Entry;
use craft\events\SetElementRouteEvent;
use yii\base\Event;
use yii\base\Module as BaseModule;

class ProductUrlModule extends BaseModule
{
    public function init()
    {
        parent::init();

        // Listen to the route event for entries
        Event::on(
            Entry::class,
            Entry::EVENT_SET_ROUTE,
            [$this, 'setProductRoute']
        );
    }

    public function setProductRoute(SetElementRouteEvent $event)
    {
        /** @var Entry $entry */
        $entry = $event->sender;

        // Only handle product section entries
        if (!isset($entry->section->handle) || $entry->section->handle !== 'product') {
            return;
        }

        // Skip if this is a draft or revision
        if ($entry->getIsDraft() || $entry->getIsRevision()) {
            return;
        }

        // Eager load the relations
        $productCategory = $entry->commonProductCategory->one();

        if (!$productCategory) {
            Craft::warning(
                "Product '{$entry->title}' (ID: {$entry->id}) is missing a product category",
                __METHOD__
            );
            return;
        }

        // Check if product has a subcategory
        $productSubcategory = $entry->commonProductSubcategory->one();

        // Build the URI based on whether subcategory exists
        if ($productSubcategory) {
            // URI with subcategory: product/category-slug/subcategory-slug/product-slug
            $uri = sprintf(
                '/product/%s/%s/%s',
                $productCategory->slug,
                $productSubcategory->slug,
                $entry->slug
            );
        } else {
            // URI without subcategory: product/category-slug/product-slug
            $uri = sprintf(
                '/product/%s/%s',
                $productCategory->slug,
                $entry->slug
            );
        }

        // Set the route
        $event->route = [
            'templates/render',
            [
                'template' => 'pages/product',
                'variables' => [
                    'entry' => $entry,
                ]
            ]
        ];

        // Update the entry's URI
        $entry->uri = $uri;
    }
}
