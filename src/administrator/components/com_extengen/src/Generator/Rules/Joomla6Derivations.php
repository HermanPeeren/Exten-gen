<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Generator\Rules;

use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;
use Yepr\Component\Extengen\Administrator\CustomCode\CustomCode;
use Yepr\Gen\Core\Rule\Registry;

/**
 * The computed half of the mapping: everything a rule cannot say as a lookup.
 *
 * A binding that reads `extensions.component.manifest.copyright` is data and
 * lives in the rule file. A binding that has to walk an entity's fields,
 * resolve each reference to another entity, decide whether that entity is a
 * value object, and build a list of foreign keys is not data, and pretending
 * otherwise would mean inventing an expression language. It stays PHP - but it
 * stays PHP *with a name*, which is the difference this step is about.
 *
 * Naming them made the back end and the front end stop looking alike. They
 * never were: `adminPageForeign` skips references to value objects and to
 * many-to-many fields, `siteIndexForeign` skips only the many-to-many ones, and
 * `siteDetailsForeign` skips nothing and returns fewer keys. Those three sat in
 * three files as three anonymous loops that read almost the same, and the
 * differences between them were invisible.
 *
 * Every one of these is pure: model in, value out, no state and no I/O. The one
 * exception is the slot warnings, which are collected rather than returned
 * because they belong in the generation log the user reads.
 *
 * @since  1.1.0
 */
final class Joomla6Derivations
{
    /**
     * Slots that hold custom code nobody generates anywhere.
     *
     * @var    string[]
     * @since  1.1.0
     */
    private array $warnings = [];

    /**
     * Entities by id, per model.
     *
     * @var    array<string, array<string|int, object>>
     * @since  1.1.0
     */
    private array $entityCache = [];

    /**
     * Fields by id, per model.
     *
     * @var    array<string, array<string|int, object>>
     * @since  1.1.0
     */
    private array $fieldCache = [];

    /**
     * The derivations, ready for the engine.
     *
     * @return  Registry
     *
     * @since   1.1.0
     */
    public function registry(): Registry
    {
        return (new Registry('derivation'))
            // The project, under the two spellings the generators have always used.
            ->register('componentName', static fn (mixed $n, object $m): string
                => (string) ($m->extensions->component->component_name ?? ''))
            ->register('componentNameUcfirst', static fn (mixed $n, object $m): string
                => ucfirst((string) ($m->extensions->component->component_name ?? '')))
            ->register('projectNameUcfirst', static fn (mixed $n, object $m): string
                => ucfirst((string) ($m->name ?? '')))

            // The component's back-end menu, and the view it opens on.
            ->register('backendIndexViews', fn (mixed $n, object $m): array => $this->indexViews($m, 'backendsection'))
            ->register('defaultBackendView', fn (mixed $n, object $m): string => $this->defaultView($m, 'backendsection'))
            ->register('defaultBackendIndexPage', fn (mixed $n, object $m): string
                => $this->firstIndexPageName($m, 'backendsection'))
            ->register('defaultFrontendIndexPage', fn (mixed $n, object $m): string
                => $this->firstIndexPageName($m, 'frontendsection'))

            // Entities.
            ->register('entityNameUcfirst', static fn (object $n): string => ucfirst((string) $n->entity_name))
            ->register('entitySlots', fn (object $n): array => $this->slots($n, 'Entity'))
            ->register('manyToMany', fn (object $n, object $m): array => $this->manyToMany($n, $m))

            // Pages, either section.
            ->register('pageName', static fn (object $n): string => ucfirst((string) $n->page_name))
            ->register('pageEntityName', fn (object $n, object $m): string
                => (string) ($this->pageEntity($n, $m)->entity_name ?? ''))
            ->register('pageEntityArray', fn (object $n, object $m): ?array
                => $this->deepArray($this->pageEntity($n, $m)))
            ->register('pageSlots', fn (object $n): array => $this->slots($n, 'Page', (string) $n->page_type))
            ->register('adminLinkPageName', fn (object $n, object $m): string => $this->linkPageName($n, $m, false))
            ->register('siteLinkPageName', fn (object $n, object $m): string => $this->linkPageName($n, $m, true))

            // Back-end pages.
            ->register('adminPageFilters', fn (object $n, object $m): array => $this->filters($n, $m, true))
            ->register('adminPagePropertyFieldNames', fn (object $n, object $m): array
                => $this->propertyFieldNames($this->pageEntity($n, $m)))
            ->register('adminPageForeign', fn (object $n, object $m): array => $this->adminForeign($n, $m))

            // Front-end pages.
            ->register('siteIndexFilters', fn (object $n, object $m): array => $this->filters($n, $m, false))
            ->register('siteIndexPropertyFieldNames', fn (object $n, object $m): array
                => $this->propertyFieldNames($this->siteIndexEntity($n, $m)))
            ->register('siteIndexForeign', fn (object $n, object $m): array => $this->siteIndexForeign($n, $m))
            ->register('siteDetailsForeign', fn (object $n, object $m): array => $this->siteDetailsForeign($n, $m));
    }

    /**
     * Slots that held code nobody generated, for the log.
     *
     * @return  string[]
     *
     * @since   1.1.0
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Custom-code regions for one node, noting any slot that goes nowhere.
     *
     * @param   object   $node      The entity or page.
     * @param   string   $owner     `Entity` or `Page`.
     * @param   ?string  $pageType  Narrows Page slots to one kind of page.
     *
     * @return  array<string, string>
     *
     * @since   1.1.0
     */
    private function slots(object $node, string $owner, ?string $pageType = null): array
    {
        $custom = new CustomCode();

        foreach ($custom->unknownSlots($node) as $slot) {
            $this->warnings[] = 'custom code for unknown slot "' . $slot . '" was not generated anywhere';
        }

        return $custom->regions($node, $owner, $pageType);
    }

    /**
     * Entities by id.
     *
     * @param   object  $model  The decoded project.
     *
     * @return  array<string|int, object>
     *
     * @since   1.1.0
     */
    private function entities(object $model): array
    {
        $key = spl_object_hash($model);

        if (!isset($this->entityCache[$key])) {
            $map = [];

            foreach ((array) ($model->datamodel ?? []) as $entity) {
                $map[$entity->entity_id] = $entity;
            }

            $this->entityCache[$key] = $map;
        }

        return $this->entityCache[$key];
    }

    /**
     * Fields by id, across every entity.
     *
     * @param   object  $model  The decoded project.
     *
     * @return  array<string|int, object>
     *
     * @since   1.1.0
     */
    private function fields(object $model): array
    {
        $key = spl_object_hash($model);

        if (!isset($this->fieldCache[$key])) {
            $map = [];

            foreach ((array) ($model->datamodel ?? []) as $entity) {
                foreach ((array) ($entity->field ?? []) as $field) {
                    $map[$field->field_id] = $field;
                }
            }

            $this->fieldCache[$key] = $map;
        }

        return $this->fieldCache[$key];
    }

    /**
     * The entity a page is about, or null when it names none.
     *
     * The back end used to read `$page->entity_ref->entity_ref0->reference`
     * after checking only that `entity_ref` existed, so a page whose entity was
     * removed - leaving the property present and empty - stopped generation
     * with a notice about `entity_ref0`. The front end already checked for
     * empty; both do now.
     *
     * @param   object  $page   The page.
     * @param   object  $model  The decoded project.
     *
     * @return  ?object
     *
     * @since   1.1.0
     */
    private function pageEntity(object $page, object $model): ?object
    {
        if (!property_exists($page, 'entity_ref') || empty($page->entity_ref)) {
            return null;
        }

        $reference = $page->entity_ref->entity_ref0->reference ?? null;

        return $reference === null ? null : ($this->entities($model)[$reference] ?? null);
    }

    /**
     * The entity the front-end index templates actually see.
     *
     * Not the page's entity, when the page has filters. The imperative version
     * reused the name `$entity` as the loop variable of its filter walk, so by
     * the time it built `propertyFieldNames` and `foreign` the name held the
     * *last filter's* entity. The back end escaped this only because it built
     * those two lists before the filter walk rather than after.
     *
     * Reproduced rather than fixed: it is what the approved output contains, so
     * fixing it here would bury a behaviour change inside a refactoring. It is
     * a one-line change in this method once somebody looks at the diff.
     *
     * @param   object  $page   The page.
     * @param   object  $model  The decoded project.
     *
     * @return  ?object
     *
     * @since   1.1.0
     */
    private function siteIndexEntity(object $page, object $model): ?object
    {
        $entity   = $this->pageEntity($page, $model);
        $entities = $this->entities($model);

        foreach ((array) ($page->filters ?? []) as $filter) {
            $entity = $entities[$filter->entity_reference] ?? $entity;
        }

        return $entity;
    }

    /**
     * An entity as nested arrays, which is what a template can loop over.
     *
     * @param   ?object  $entity  The entity.
     *
     * @return  ?array<string, mixed>
     *
     * @since   1.1.0
     */
    private function deepArray(?object $entity): ?array
    {
        if ($entity === null) {
            return null;
        }

        return json_decode((string) json_encode($entity), true);
    }

    /**
     * The page a page links to, by name.
     *
     * @param   object   $page      The page.
     * @param   object   $model     The decoded project.
     * @param   boolean  $fallback  Fall back to the page's own name when it links nowhere.
     *
     * @return  string
     *
     * @since   1.1.0
     */
    private function linkPageName(object $page, object $model, bool $fallback): string
    {
        $own = ucfirst((string) $page->page_name);

        if (empty($page->links)) {
            return $fallback ? $own : '';
        }

        $reference = $page->links->links0->target_page->page_reference ?? null;

        foreach ((array) ($model->pages ?? []) as $candidate) {
            if ($candidate->page_id === $reference) {
                return ucfirst((string) $candidate->page_name);
            }
        }

        return $fallback ? $own : '';
    }

    /**
     * The index pages one section lists, by name.
     *
     * @param   object  $model    The decoded project.
     * @param   string  $section  `backendsection` or `frontendsection`.
     *
     * @return  string[]
     *
     * @since   1.1.0
     */
    private function indexViews(object $model, string $section): array
    {
        $names = [];

        foreach (Joomla6Selectors::pages($model, $section) as $page) {
            if ($page->page_type === 'indexpage') {
                $names[] = $page->page_name;
            }
        }

        return $names;
    }

    /**
     * The page the component's menu item opens, by name.
     *
     * @param   object  $model    The decoded project.
     * @param   string  $section  `backendsection` or `frontendsection`.
     *
     * @return  string
     *
     * @since   1.1.0
     */
    private function defaultView(object $model, string $section): string
    {
        $default = $model->extensions->component->Sections->{$section === 'backendsection' ? 'backenddefaultpage' : 'frontenddefaultpage'}->page_reference ?? null;

        foreach ((array) ($model->pages ?? []) as $page) {
            if ($page->page_id === $default) {
                return (string) $page->page_name;
            }
        }

        return '';
    }

    /**
     * The first index page in a section, which is the view its DisplayController falls back to.
     *
     * @param   object  $model    The decoded project.
     * @param   string  $section  `backendsection` or `frontendsection`.
     *
     * @return  string
     *
     * @since   1.1.0
     */
    private function firstIndexPageName(object $model, string $section): string
    {
        foreach (Joomla6Selectors::pages($model, $section) as $page) {
            if ($page->page_type === 'indexpage') {
                return ucfirst((string) $page->page_name);
            }
        }

        return '';
    }

    /**
     * The names of an entity's plain property fields.
     *
     * @param   ?object  $entity  The entity, or null for a page that names none.
     *
     * @return  string[]
     *
     * @since   1.1.0
     */
    private function propertyFieldNames(?object $entity): array
    {
        $names = [];

        foreach ((array) ($entity->field ?? []) as $field) {
            if (FieldKind::isProperty($field)) {
                $names[] = $field->field_name;
            }
        }

        return $names;
    }

    /**
     * How a foreign entity is shown when it is referred to.
     *
     * The first property marked `default_ref_display`, or the id when the
     * entity marks none - showing a number beats showing nothing.
     *
     * @param   object  $entity  The referred-to entity.
     *
     * @return  string
     *
     * @since   1.1.0
     */
    private function displayField(object $entity): string
    {
        foreach ((array) ($entity->field ?? []) as $field) {
            if (FieldKind::isProperty($field) && property_exists(FieldKind::property($field), 'default_ref_display')) {
                return (string) $field->field_name;
            }
        }

        return 'id';
    }

    /**
     * A page's list filters, as field name and the column it filters on.
     *
     * @param   object   $page            The page.
     * @param   object   $model           The decoded project.
     * @param   boolean  $skipValueObject  Leave out references to embedded objects.
     *
     * @return  array<int, array<string, string>>
     *
     * @since   1.1.0
     */
    private function filters(object $page, object $model, bool $skipValueObject): array
    {
        $entities = $this->entities($model);
        $fields   = $this->fields($model);
        $filters  = [];

        foreach ((array) ($page->filters ?? []) as $filter) {
            $field = $fields[$filter->field_reference] ?? null;

            if ($field === null) {
                continue;
            }

            if (FieldKind::isProperty($field)) {
                $filters[] = ['fieldName' => $field->field_name, 'columnName' => $field->field_name];

                continue;
            }

            if (!FieldKind::isReference($field)) {
                continue;
            }

            $referred = $entities[FieldKind::reference($field)->reference ?? ''] ?? null;

            if ($referred === null || ($skipValueObject && property_exists($referred, 'isvalueobject'))) {
                continue;
            }

            $filters[] = [
                'fieldName'  => $field->field_name,
                'columnName' => strtolower((string) $referred->entity_name) . '_id',
            ];
        }

        return $filters;
    }

    /**
     * The back end's joined columns: one per reference to a real entity, singular.
     *
     * Many-to-many references go to a junction table instead, and references to
     * value objects are stored inline, so neither is a join.
     *
     * @param   object  $page   The page.
     * @param   object  $model  The decoded project.
     *
     * @return  array<int, array<string, string>>
     *
     * @since   1.1.0
     */
    private function adminForeign(object $page, object $model): array
    {
        $entities = $this->entities($model);
        $foreign  = [];

        foreach ((array) ($this->pageEntity($page, $model)->field ?? []) as $field) {
            if (!FieldKind::isReference($field)) {
                continue;
            }

            $referred = $entities[FieldKind::reference($field)->reference ?? ''] ?? null;

            if (
                $referred === null
                || property_exists($referred, 'isvalueobject')
                || property_exists(FieldKind::reference($field), 'ismultiple')
            ) {
                continue;
            }

            $foreign[] = [
                'fieldName'        => $field->field_name,
                'columnName'       => strtolower((string) $referred->entity_name) . '_id',
                'foreignFieldName' => $this->displayField($referred),
                'refEntityName'    => $referred->entity_name,
            ];
        }

        return $foreign;
    }

    /**
     * The front-end list's joined columns.
     *
     * Same shape as the back end's, one difference: a reference to a value
     * object is joined here rather than skipped. Whether that is right is a
     * question for the step that reads these side by side; it is what the
     * approved output contains.
     *
     * @param   object  $page   The page.
     * @param   object  $model  The decoded project.
     *
     * @return  array<int, array<string, string>>
     *
     * @since   1.1.0
     */
    private function siteIndexForeign(object $page, object $model): array
    {
        $entities = $this->entities($model);
        $foreign  = [];

        foreach ((array) ($this->siteIndexEntity($page, $model)->field ?? []) as $field) {
            if (!FieldKind::isReference($field) || property_exists(FieldKind::reference($field), 'ismultiple')) {
                continue;
            }

            $referred = $entities[FieldKind::reference($field)->reference ?? ''] ?? null;

            if ($referred === null) {
                continue;
            }

            $foreign[] = [
                'fieldName'        => $field->field_name,
                'columnName'       => strtolower((string) $referred->entity_name) . '_id',
                'foreignFieldName' => $this->displayField($referred),
                'refEntityName'    => $referred->entity_name,
            ];
        }

        return $foreign;
    }

    /**
     * The front-end details page's field-to-column map.
     *
     * Every reference, including the multiple ones and the embedded ones, and
     * only two keys rather than four.
     *
     * @param   object  $page   The page.
     * @param   object  $model  The decoded project.
     *
     * @return  array<int, array<string, string>>
     *
     * @since   1.1.0
     */
    private function siteDetailsForeign(object $page, object $model): array
    {
        $entities = $this->entities($model);
        $foreign  = [];

        foreach ((array) ($this->pageEntity($page, $model)->field ?? []) as $field) {
            if (!FieldKind::isReference($field)) {
                continue;
            }

            $referred = $entities[FieldKind::reference($field)->reference ?? ''] ?? null;

            if ($referred === null) {
                continue;
            }

            $foreign[] = [
                'fieldName'  => $field->field_name,
                'columnName' => strtolower((string) $referred->entity_name) . '_id',
            ];
        }

        return $foreign;
    }

    /**
     * One entry per many-to-many reference on an entity.
     *
     * Feeds the four table fragments, which are rendered once per relation and
     * concatenated into the generated Table class.
     *
     * @param   object  $entity  The entity.
     * @param   object  $model   The decoded project.
     *
     * @return  array<int, array<string, string>>
     *
     * @since   1.1.0
     */
    private function manyToMany(object $entity, object $model): array
    {
        $entities = $this->entities($model);
        $from     = strtolower((string) $entity->entity_name);
        $relations = [];

        foreach ((array) ($entity->field ?? []) as $field) {
            if (!FieldKind::isReference($field) || !property_exists(FieldKind::reference($field), 'ismultiple')) {
                continue;
            }

            $referred = $entities[FieldKind::reference($field)->reference ?? ''] ?? null;

            if ($referred === null || property_exists($referred, 'isvalueobject')) {
                continue;
            }

            $to = strtolower((string) $referred->entity_name);

            $relations[] = [
                'relatedEntityName' => ucfirst((string) $referred->entity_name),
                'pivotTable'        => $from > $to ? $to . '_' . $from : $from . '_' . $to,
            ];
        }

        return $relations;
    }
}
