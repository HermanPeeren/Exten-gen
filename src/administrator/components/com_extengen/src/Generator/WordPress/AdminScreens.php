<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\WordPress;

/**
 * The screens: a menu, a list per index page, a form per detail page.
 *
 * ER1's pages map onto WordPress about as well as they map onto Joomla, and
 * differently. An index page becomes a `WP_List_Table` subclass and a menu
 * entry; a details page becomes a partial the screen includes. There is no
 * controller, no view class and no MVC: WordPress admin pages are a callback
 * registered against `admin_menu`, and everything else is the plugin's own
 * business.
 *
 * Which is exactly why the same model produces so different a file set. The
 * pages, the entities and the columns are the same nouns; what a page *is*
 * differs entirely, and that difference is what a target holds.
 *
 * Pages whose entity the model does not carry are skipped rather than
 * generated against nothing - the same dangling-reference case the Joomla
 * selectors handle, arrived at here through a page rather than a section.
 *
 * @since  1.4.0
 */
final class AdminScreens extends WordPressGenerator
{
    /**
     * @return  string[]  A log of what was produced.
     *
     * @since   1.4.0
     */
    protected function generateFiles(): array
    {
        $screens = $this->screens('indexpage');
        $forms   = $this->screens('detailspage');

        if ($screens === [] && $forms === []) {
            return [];
        }

        $log = $this->generateFileWithTemplate(
            'plugin/',
            'admin.php.twig',
            $this->pluginRoot() . 'admin/',
            'class-' . $this->slug() . '-admin.php',
            $this->header() + ['screens' => $screens]
        );

        foreach ($screens as $screen) {
            $log = array_merge($log, $this->generateFileWithTemplate(
                'plugin/',
                'list-table.php.twig',
                $this->pluginRoot() . 'admin/',
                'class-' . $this->slug() . '-' . $screen['slug'] . '-table.php',
                $this->header() + ['screen' => $screen]
            ));
        }

        foreach ($forms as $form) {
            $log = array_merge($log, $this->generateFileWithTemplate(
                'plugin/',
                'edit-form.php.twig',
                $this->pluginRoot() . 'admin/partials/',
                $form['slug'] . '-edit.php',
                $this->header() + ['form' => $form]
            ));
        }

        return $log;
    }

    /**
     * The pages of one kind, as the templates want them.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since  1.4.0
     */
    private function screens(string $kind): array
    {
        $entities = [];

        foreach ($this->project->entities() as $entity) {
            $entities[(string) ($entity->entity_id ?? '')] = $entity;
        }

        $screens = [];

        foreach ($this->project->pages() as $page) {
            if ((string) ($page->page_type ?? '') !== $kind) {
                continue;
            }

            $entityId = (string) ($page->entity_ref->entity_ref0->reference ?? '');
            $entity   = $entities[$entityId] ?? null;

            if ($entity === null) {
                continue;
            }

            $name = (string) $page->page_name;

            $screens[] = [
                'label'    => $name,
                'slug'     => self::slugify($name),
                'singular' => self::slugify((string) $entity->entity_name),
                'class'    => str_replace(' ', '', ucwords(str_replace('_', ' ', self::slugify($name)))),
                'method'   => 'screen_' . self::slugify($name),
                'table'    => $this->tableName((string) $entity->entity_name),
                'columns'  => $this->columns($page, $entity),
                'fields'   => $this->fields($entity),
            ];
        }

        return $screens;
    }

    /**
     * What a list shows: the page's presentation columns, or everything.
     *
     * @return array<int, array<string, string>>
     *
     * @since  1.4.0
     */
    private function columns(object $page, object $entity): array
    {
        $byId = [];

        foreach ((array) ($entity->field ?? []) as $field) {
            $byId[(string) ($field->field_id ?? '')] = $field;
        }

        $chosen = [];

        foreach ((array) ($page->presentationcolumns ?? []) as $column) {
            $field = $byId[(string) ($column->field_reference ?? '')] ?? null;

            if ($field !== null) {
                $chosen[] = $field;
            }
        }

        if ($chosen === []) {
            $chosen = array_values(array_filter(
                $byId,
                static fn (object $f): bool => ($f->field_type ?? '') === 'property'
            ));
        }

        $columns = [];

        foreach ($chosen as $field) {
            $columns[] = [
                'name'  => self::slugify((string) $field->field_name),
                'label' => ucfirst((string) $field->field_name),
            ];
        }

        return $columns;
    }

    /**
     * What a form edits: every property of the entity.
     *
     * @return array<int, array<string, string>>
     *
     * @since  1.4.0
     */
    private function fields(object $entity): array
    {
        $fields = [];

        foreach ((array) ($entity->field ?? []) as $field) {
            if (($field->field_type ?? '') !== 'property') {
                continue;
            }

            $type = (string) ($field->property->type ?? '');

            $fields[] = [
                'name'  => self::slugify((string) $field->field_name),
                'label' => ucfirst((string) $field->field_name),
                'input' => self::INPUT_TYPES[$type] ?? 'text',
            ];
        }

        return $fields;
    }
}
