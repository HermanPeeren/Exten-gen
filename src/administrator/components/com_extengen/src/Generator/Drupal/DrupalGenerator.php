<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Drupal;

use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;
use Yepr\Component\Extengen\Administrator\Generator\Generator;

/**
 * What every Drupal generator needs to know about the module being written.
 *
 * Drupal's naming is the strictest of the three targets and the least
 * forgiving: the *machine name* is lower case with underscores, it is the
 * directory, the `.info.yml` file name, the namespace segment, the prefix of
 * every route, every menu link and every hook function - and a module whose
 * directory and `.info.yml` disagree is a module Drupal does not see at all.
 * There is no error; it simply is not in the list.
 *
 * **Nothing here knows what a Joomla is**, which is the point of having targets
 * rather than a nicety. The model these read is ER1 - entities, fields, pages -
 * and ER1 has never mentioned a CMS.
 *
 * @since  1.4.0
 */
abstract class DrupalGenerator extends Generator
{
    /**
     * How an ER1 property type is described to Drupal's schema API.
     *
     * The third answer this repository now has to "what is a column", and the
     * one that is not SQL at all: Drupal is handed a PHP array and builds the
     * statement itself, for whichever driver the site runs on. So there is no
     * `varchar(255)` here, there is a type and a length, and the difference is
     * what lets the same module install on MySQL, PostgreSQL and SQLite.
     *
     * Written as PHP source rather than as values, because that is what the
     * template interpolates - a schema is code in a `.install` file.
     *
     * @var    array<string, array<string, string>>
     * @since  1.4.0
     */
    protected const COLUMN_TYPES = [
        'Short_Text' => ['type' => "'varchar'", 'length' => '255', 'not null' => 'TRUE', 'default' => "''"],
        'Text'       => ['type' => "'text'", 'size' => "'big'", 'not null' => 'FALSE'],
        'Integer'    => ['type' => "'int'", 'not null' => 'TRUE', 'default' => '0'],
        'Boolean'    => ['type' => "'int'", 'size' => "'tiny'", 'not null' => 'TRUE', 'default' => '0'],
        'Decimal'    => ['type' => "'numeric'", 'precision' => '10', 'scale' => '2', 'not null' => 'FALSE'],
        'Currency'   => ['type' => "'numeric'", 'precision' => '10', 'scale' => '2', 'not null' => 'FALSE'],
        'Float'      => ['type' => "'float'", 'not null' => 'FALSE'],
        // Dates are stored as the ISO strings the forms produce. Drupal's own
        // entities use an integer timestamp, which is right for something with
        // a timezone behind it and wrong for a date somebody typed.
        'Date'       => ['type' => "'varchar'", 'length' => '20', 'not null' => 'FALSE'],
        'Time'       => ['type' => "'varchar'", 'length' => '20', 'not null' => 'FALSE'],
        'DateTime'   => ['type' => "'varchar'", 'length' => '32', 'not null' => 'FALSE'],
        'File'       => ['type' => "'varchar'", 'length' => '255', 'not null' => 'FALSE'],
        'Link'       => ['type' => "'varchar'", 'length' => '255', 'not null' => 'FALSE'],
        'Image'      => ['type' => "'varchar'", 'length' => '255', 'not null' => 'FALSE'],
    ];

    /**
     * Which Form API element edits a value of this type.
     *
     * @var    array<string, string>
     * @since  1.4.0
     */
    protected const ELEMENT_TYPES = [
        'Text'     => 'textarea',
        'Integer'  => 'number',
        'Decimal'  => 'number',
        'Currency' => 'number',
        'Float'    => 'number',
        'Boolean'  => 'checkbox',
        'Date'     => 'date',
        'DateTime' => 'datetime',
        'Link'     => 'url',
    ];

    /**
     * The module's readable name, which is the component's.
     *
     * @since  1.4.0
     */
    protected function moduleName(): string
    {
        return $this->componentName;
    }

    /**
     * The machine name: the directory, the namespace, every route prefix.
     *
     * @since  1.4.0
     */
    protected function machineName(): string
    {
        return self::machinify($this->componentName);
    }

    /**
     * Where the module's files go, inside the produced file set.
     *
     * @since  1.4.0
     */
    protected function moduleRoot(): string
    {
        return $this->machineName() . '/';
    }

    /**
     * The one permission everything this module adds is behind.
     *
     * @since  1.4.0
     */
    protected function permission(): string
    {
        return 'administer ' . str_replace('_', ' ', $this->machineName());
    }

    /**
     * The table an entity is stored in.
     *
     * Prefixed with the module's machine name because Drupal puts every
     * module's tables in one database beside the core ones, and `talk` is a
     * name somebody else will want.
     *
     * @since  1.4.0
     */
    protected function tableName(string $entityName): string
    {
        return $this->machineName() . '_' . self::machinify($entityName);
    }

    /**
     * Lower case, underscores, starting with a letter.
     *
     * Drupal rejects a machine name that starts with a digit, so a project
     * called "7Wonders" gets an underscore in front rather than a module
     * nobody can enable.
     *
     * @since  1.4.0
     */
    protected static function machinify(string $value): string
    {
        $name = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '', '_'));

        return $name === '' || ctype_digit($name[0]) ? '_' . $name : $name;
    }

    /**
     * PascalCase, for a class name.
     *
     * @since  1.4.0
     */
    protected static function classify(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', self::machinify($value))));
    }

    /**
     * The entities, as the templates want them.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since  1.4.0
     */
    protected function entities(): array
    {
        $entities = [];

        foreach ($this->project->entities() as $entity) {
            $columns = [];

            foreach ((array) ($entity->field ?? []) as $field) {
                if (!FieldKind::isProperty($field)) {
                    // A reference is a column of its own shape and belongs with
                    // the join it implies, which this target does not do yet.
                    continue;
                }

                $columns[] = [
                    'name'  => self::machinify((string) $field->field_name),
                    'label' => ucfirst((string) $field->field_name),
                    'spec'  => self::COLUMN_TYPES[(string) (FieldKind::property($field)->type ?? '')]
                        ?? self::COLUMN_TYPES['Text'],
                ];
            }

            $entities[] = [
                'name'    => (string) $entity->entity_name,
                'label'   => ucfirst((string) $entity->entity_name),
                'table'   => $this->tableName((string) $entity->entity_name),
                'columns' => $columns,
            ];
        }

        return $entities;
    }

    /**
     * The pages of one kind, as the templates want them.
     *
     * A page whose entity the model does not carry is skipped rather than
     * generated against nothing - the same dangling-reference case the Joomla
     * selectors handle, arrived at through a page rather than a section.
     *
     * @return array<int, array<string, mixed>>
     *
     * @since  1.4.0
     */
    protected function screens(string $kind): array
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

            $entity = $entities[(string) ($page->entity_ref->entity_ref0->reference ?? '')] ?? null;

            if ($entity === null) {
                continue;
            }

            $name = (string) $page->page_name;

            $screens[] = [
                'label'   => $name,
                'path'    => self::machinify($name),
                'route'   => self::machinify($name),
                'class'   => self::classify($name),
                'table'   => $this->tableName((string) $entity->entity_name),
                'columns' => $this->columns($page, $entity),
                'fields'  => $this->fields($entity),
            ];
        }

        return $screens;
    }

    /**
     * What a listing shows: the page's presentation columns, or everything.
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
                static fn (object $f): bool => FieldKind::isProperty($f)
            ));
        }

        return array_map(
            static fn (object $field): array => [
                'name'  => self::machinify((string) $field->field_name),
                'label' => ucfirst((string) $field->field_name),
            ],
            $chosen
        );
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
            if (!FieldKind::isProperty($field)) {
                continue;
            }

            $fields[] = [
                'name'    => self::machinify((string) $field->field_name),
                'label'   => ucfirst((string) $field->field_name),
                'element' => self::ELEMENT_TYPES[(string) (FieldKind::property($field)->type ?? '')] ?? 'textfield',
            ];
        }

        return $fields;
    }

    /**
     * The values every template needs, with usable fallbacks.
     *
     * A `.info.yml` with an empty name is a module Drupal lists as broken, so
     * nothing here may come out blank.
     *
     * @return array<string, string>
     *
     * @since  1.4.0
     */
    protected function header(): array
    {
        return [
            'moduleName'  => $this->moduleName(),
            'machine'     => $this->machineName(),
            'permission'  => $this->permission(),
            'package'     => 'Custom',
            'description' => trim((string) ($this->project->component()->component_description ?? ''))
                ?: $this->moduleName() . '.',
        ];
    }
}
