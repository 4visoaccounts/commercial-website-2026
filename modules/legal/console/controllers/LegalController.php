<?php

namespace modules\legal\console\controllers;

use Craft;
use craft\ckeditor\Field as CkeditorField;
use craft\console\Controller;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Matrix;
use craft\fields\Table;
use craft\helpers\Console;
use craft\helpers\Json;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use yii\console\ExitCode;

/**
 * Legal pages: structure setup + content import.
 */
class LegalController extends Controller
{
    public $defaultAction = 'import';

    /** Section handle => [name, uri] */
    private const SINGLES = [
        'privacyPolicy' => ['Privacy Policy', 'privacy'],
        'termsAndConditions' => ['Terms and Conditions', 'terms-and-conditions'],
    ];

    private const TEMPLATE = 'legal/_entry.twig';
    private const PAGE_ENTRY_TYPE = 'legalPage';
    private const SECTION_ENTRY_TYPE = 'commonLegalSection';

    /**
     * Creates the fields, entry types and single sections for the legal pages.
     * Idempotent: anything that already exists is left untouched.
     * Run locally once; production receives the result via config/project (project-config/apply).
     */
    public function actionSetup(): int
    {
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $this->stderr("allowAdminChanges is disabled. Run this locally and deploy config/project instead.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // --- Fields -----------------------------------------------------------------------
        $description = $fields->getFieldByHandle('commonDescription');
        $subtitle = $fields->getFieldByHandle('commonSubtitle');
        $footnote = $fields->getFieldByHandle('commonFootnote');
        $anchor = $fields->getFieldByHandle('commonId');
        $seo = $fields->getFieldByHandle('seo');

        foreach (['commonDescription' => $description, 'commonSubtitle' => $subtitle, 'commonFootnote' => $footnote, 'commonId' => $anchor, 'seo' => $seo] as $handle => $field) {
            if (!$field) {
                $this->stderr("Expected existing field `$handle` not found.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        // Table: label/value rows shown under the page title (Version, In effect from, ...)
        $metaRows = $fields->getFieldByHandle('commonMetaRows');
        if (!$metaRows) {
            $metaRows = new Table([
                'name' => 'Meta rows',
                'handle' => 'commonMetaRows',
                'instructions' => 'Label / value pairs shown under the title, e.g. Version, In effect from, Last updated.',
                'addRowLabel' => 'Add a row',
                'columns' => [
                    'col1' => ['heading' => 'Label', 'handle' => 'label', 'type' => 'singleline', 'width' => '35%'],
                    'col2' => ['heading' => 'Value', 'handle' => 'value', 'type' => 'singleline', 'width' => ''],
                ],
                'defaults' => [],
            ]);
            $this->saveField($metaRows);
        }

        // CKEditor: highlighted intro box. Cloned from commonDescription so editor config matches.
        $callout = $fields->getFieldByHandle('commonCallout');
        if (!$callout) {
            /** @var CkeditorField $callout */
            $callout = clone $description;
            $callout->id = null;
            $callout->uid = null;
            $callout->columnSuffix = null;
            $callout->name = 'Callout';
            $callout->handle = 'commonCallout';
            $callout->instructions = 'Highlighted intro box shown above the first section.';
            $callout->searchable = true;
            $this->saveField($callout);
        }

        // --- Section block entry type ------------------------------------------------------
        $sectionType = $entries->getEntryTypeByHandle(self::SECTION_ENTRY_TYPE);
        if (!$sectionType) {
            $sectionType = new EntryType([
                'name' => 'Legal section',
                'handle' => self::SECTION_ENTRY_TYPE,
                'hasTitleField' => true,
                'showSlugField' => false,
                'showStatusField' => true,
                'icon' => 'section',
            ]);
            $sectionType->setFieldLayout($this->buildLayout([
                [
                    'name' => 'Content',
                    'elements' => [
                        new EntryTitleField(['label' => 'Section title', 'required' => true]),
                        new CustomField($anchor, [
                            'label' => 'Anchor',
                            'instructions' => 'Used for the table of contents link (e.g. `who-we-are`). Lowercase, no spaces. Falls back to the slug.',
                            'width' => 50,
                        ]),
                        new CustomField($description, ['label' => 'Content']),
                    ],
                ],
            ]));
            $this->saveEntryType($sectionType);
        }

        // Matrix of sections
        $sections = $fields->getFieldByHandle('commonSections');
        if (!$sections) {
            $sections = new Matrix([
                'name' => 'Sections',
                'handle' => 'commonSections',
                'instructions' => 'Numbered sections. The table of contents is generated from these.',
                'propagationMethod' => PropagationMethod::All,
                'viewMode' => Matrix::VIEW_MODE_BLOCKS,
                'createButtonLabel' => 'Add section',
            ]);
            $sections->setEntryTypes([$sectionType]);
            $this->saveField($sections);
        }

        // --- Page entry type ---------------------------------------------------------------
        $pageType = $entries->getEntryTypeByHandle(self::PAGE_ENTRY_TYPE);
        if (!$pageType) {
            $pageType = new EntryType([
                'name' => 'Legal page',
                'handle' => self::PAGE_ENTRY_TYPE,
                'hasTitleField' => true,
                'showSlugField' => false,
                'showStatusField' => true,
                'icon' => 'scale-balanced',
                'color' => 'gray',
            ]);
            $pageType->setFieldLayout($this->buildLayout([
                [
                    'name' => 'Content',
                    'elements' => [
                        new EntryTitleField(['required' => true]),
                        new CustomField($subtitle, [
                            'label' => 'Kicker',
                            'instructions' => 'Small label above the title, e.g. "Privacy notice".',
                            'width' => 50,
                        ]),
                        new CustomField($metaRows, ['label' => 'Document details']),
                        new CustomField($description, [
                            'label' => 'Intro',
                            'instructions' => 'One or two sentences shown in the header.',
                        ]),
                        new CustomField($callout, ['label' => 'Callout']),
                        new CustomField($sections, ['label' => 'Sections']),
                        new CustomField($footnote, [
                            'label' => 'Footer line',
                            'instructions' => 'Shown at the bottom after the copyright year, e.g. "4Viso BV · Science Park Antwerp, …".',
                        ]),
                    ],
                ],
                [
                    'name' => 'Seo',
                    'elements' => [new CustomField($seo)],
                ],
            ]));
            $this->saveEntryType($pageType);
        }

        // --- Singles -----------------------------------------------------------------------
        foreach (self::SINGLES as $handle => [$name, $uri]) {
            if ($entries->getSectionByHandle($handle)) {
                $this->stdout("Section `$handle` exists, skipping.\n", Console::FG_GREY);
                continue;
            }

            $section = new Section([
                'name' => $name,
                'handle' => $handle,
                'type' => Section::TYPE_SINGLE,
                'enableVersioning' => true,
                'propagationMethod' => PropagationMethod::All,
                'maxAuthors' => 1,
                'previewTargets' => [
                    ['label' => 'Primary entry page', 'urlFormat' => '{url}', 'refresh' => '1'],
                ],
            ]);

            $siteSettings = [];
            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $siteSettings[] = new Section_SiteSettings([
                    'siteId' => $site->id,
                    'enabledByDefault' => true,
                    'hasUrls' => true,
                    'uriFormat' => $uri,
                    'template' => self::TEMPLATE,
                ]);
            }
            $section->setSiteSettings($siteSettings);
            $section->setEntryTypes([$pageType]);

            if (!$entries->saveSection($section)) {
                $this->stderr("Could not save section `$handle`: " . implode(' ', $section->getErrorSummary(true)) . "\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("Created single `$handle` at /$uri\n", Console::FG_GREEN);
        }

        $this->stdout("Setup done. Commit config/project and run `php craft legal/import`.\n", Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Imports seeds/legal/*.json into the matching single entries.
     * Replaces the full content of each page (title, header fields, all sections).
     *
     * @param string|null $only Optional seed file name without extension (e.g. `privacy-policy`)
     */
    public function actionImport(?string $only = null): int
    {
        $dir = Craft::getAlias('@root/seeds/legal');
        $files = glob($dir . '/*.json') ?: [];

        if ($only !== null) {
            $files = array_filter($files, fn(string $f) => basename($f, '.json') === $only);
        }

        if (!$files) {
            $this->stderr("No seed files found in $dir\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entries = Craft::$app->getEntries();
        $elements = Craft::$app->getElements();

        foreach ($files as $file) {
            $data = Json::decode(file_get_contents($file));
            $handle = $data['section'] ?? null;
            $section = $handle ? $entries->getSectionByHandle($handle) : null;

            if (!$section) {
                $this->stderr("Section `$handle` not found for " . basename($file) . ". Run `legal/setup` / project-config/apply first.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $sectionType = $entries->getEntryTypeByHandle(self::SECTION_ENTRY_TYPE);
            if (!$sectionType) {
                $this->stderr("Entry type `" . self::SECTION_ENTRY_TYPE . "` not found.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $entry = Entry::find()
                    ->sectionId($section->id)
                    ->siteId($site->id)
                    ->status(null)
                    ->one();

                if (!$entry) {
                    $entry = new Entry([
                        'sectionId' => $section->id,
                        'typeId' => $section->getEntryTypes()[0]->id,
                        'siteId' => $site->id,
                        'enabled' => true,
                    ]);
                }

                $entry->title = $data['title'] ?? $section->name;

                $fieldValues = $data['fields'] ?? [];
                if (!empty($data['seo'])) {
                    $fieldValues['seo'] = $data['seo'];
                }

                $blocks = [];
                foreach ($data['sections'] ?? [] as $i => $block) {
                    $blocks['new' . ($i + 1)] = [
                        'type' => $sectionType->handle,
                        'enabled' => true,
                        'title' => $block['title'] ?? '',
                        'fields' => [
                            'commonId' => $block['id'] ?? '',
                            'commonDescription' => $block['body'] ?? '',
                        ],
                    ];
                }
                $fieldValues['commonSections'] = $blocks;

                $entry->setFieldValues($fieldValues);
                $entry->setScenario(Entry::SCENARIO_LIVE);

                if (!$elements->saveElement($entry)) {
                    $this->stderr("Could not save `$handle` ({$site->handle}): " . implode(' ', $entry->getErrorSummary(true)) . "\n", Console::FG_RED);
                    return ExitCode::UNSPECIFIED_ERROR;
                }

                $this->stdout(sprintf("Imported %s → %s (%d sections) %s\n", basename($file), $handle, count($blocks), $entry->getUrl()), Console::FG_GREEN);
            }
        }

        return ExitCode::OK;
    }

    // ---------------------------------------------------------------------------------------

    /**
     * @param array<int, array{name: string, elements: array}> $tabs
     */
    private function buildLayout(array $tabs): FieldLayout
    {
        $layout = new FieldLayout(['type' => Entry::class]);
        $tabModels = [];
        foreach ($tabs as $tab) {
            $tabModel = new FieldLayoutTab(['name' => $tab['name'], 'layout' => $layout]);
            $tabModel->setElements($tab['elements']);
            $tabModels[] = $tabModel;
        }
        $layout->setTabs($tabModels);
        return $layout;
    }

    private function saveField($field): void
    {
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \RuntimeException("Could not save field `{$field->handle}`: " . implode(' ', $field->getErrorSummary(true)));
        }
        $this->stdout("Created field `{$field->handle}`\n", Console::FG_GREEN);
    }

    private function saveEntryType(EntryType $entryType): void
    {
        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \RuntimeException("Could not save entry type `{$entryType->handle}`: " . implode(' ', $entryType->getErrorSummary(true)));
        }
        $this->stdout("Created entry type `{$entryType->handle}`\n", Console::FG_GREEN);
    }
}
