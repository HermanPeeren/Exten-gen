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

use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;
use Yepr\Component\Extengen\Administrator\Generator\Generator;

/**
 * What every WordPress generator needs to know about the plugin being written.
 *
 * A plugin's names are all derived from one thing and derived differently:
 * `Conference` is the readable name, `conference` is the slug that appears in
 * the directory, the text domain, the option key and every menu URL, `Conference`
 * again is the class prefix, and `CONFERENCE` is the constant prefix. Four
 * spellings of one word, and getting one of them wrong produces a plugin that
 * activates and then cannot find itself.
 *
 * **Nothing here knows what a Joomla is**, and that is the point of 4.4 rather
 * than a nicety. The model these read is ER1 - entities, fields, pages - and
 * ER1 does not mention a CMS. The shared `Generator` base gives a renderer and
 * the file collection; everything below that is this target's own.
 *
 * @since  1.4.0
 */
abstract class WordPressGenerator extends Generator
{
    /**
     * How an ER1 property type is stored, in the dialect dbDelta reads.
     *
     * Deliberately not shared with the Joomla target's map, which carries
     * Joomla's own habits - `datetime NOT NULL DEFAULT '0000-00-00 00:00:00'`
     * is a Joomla-ism that MySQL 8 in strict mode rejects outright, and
     * WordPress runs on MySQL 8 far more often than Joomla does. Two targets,
     * two answers, which is what having targets is for.
     *
     * @var    array<string, string>
     * @since  1.4.0
     */
    protected const COLUMN_TYPES = [
        'Integer'    => 'bigint(20) NOT NULL DEFAULT 0',
        'Boolean'    => 'tinyint(1) NOT NULL DEFAULT 0',
        'Text'       => 'longtext',
        'Decimal'    => 'decimal(10,2) NOT NULL DEFAULT 0',
        'Currency'   => 'decimal(10,2) NOT NULL DEFAULT 0',
        'Float'      => 'float NOT NULL DEFAULT 0',
        'Short_Text' => "varchar(255) NOT NULL DEFAULT ''",
        'Time'       => 'time DEFAULT NULL',
        'Date'       => 'date DEFAULT NULL',
        'DateTime'   => 'datetime DEFAULT NULL',
        'File'       => "varchar(255) NOT NULL DEFAULT ''",
        'Link'       => "varchar(255) NOT NULL DEFAULT ''",
        'Image'      => "varchar(255) NOT NULL DEFAULT ''",
    ];

    /**
     * Which HTML input edits a value of this type.
     *
     * @var    array<string, string>
     * @since  1.4.0
     */
    protected const INPUT_TYPES = [
        'Text'     => 'textarea',
        'Integer'  => 'number',
        'Decimal'  => 'number',
        'Currency' => 'number',
        'Float'    => 'number',
        'Date'     => 'date',
        'Time'     => 'time',
        'DateTime' => 'datetime-local',
        'Link'     => 'url',
    ];

    /**
     * The plugin's readable name, which is the component's.
     *
     * @since  1.4.0
     */
    protected function pluginName(): string
    {
        return $this->componentName;
    }

    /**
     * The slug: the directory, the text domain, the option key, every menu URL.
     *
     * @since  1.4.0
     */
    protected function slug(): string
    {
        return self::slugify($this->componentName);
    }

    /**
     * The prefix every generated class carries.
     *
     * WordPress has no namespaces by convention - a plugin's classes sit in the
     * global one and are kept apart by their names, which is why this exists at
     * all and why the Joomla target has no counterpart for it.
     *
     * @since  1.4.0
     */
    protected function classPrefix(): string
    {
        return ucfirst($this->componentName);
    }

    /**
     * The prefix every generated constant carries.
     *
     * @since  1.4.0
     */
    protected function constantPrefix(): string
    {
        return strtoupper(self::slugify($this->componentName));
    }

    /**
     * Where the plugin's files go, inside the produced file set.
     *
     * @since  1.4.0
     */
    protected function pluginRoot(): string
    {
        return $this->slug() . '/';
    }

    /**
     * The table an entity is stored in, without the `$wpdb->prefix`.
     *
     * Prefixed with the plugin's slug because WordPress puts every plugin's
     * tables in one database beside the core ones, so `talk` would collide with
     * the next plugin that has talks in it.
     *
     * @since  1.4.0
     */
    protected function tableName(string $entityName): string
    {
        return self::slugify($this->componentName) . '_' . self::slugify($entityName);
    }

    /**
     * Lower case, and anything that is not a letter or a digit becomes a dash.
     *
     * @since  1.4.0
     */
    protected static function slugify(string $value): string
    {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '');

        return trim($slug, '_');
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
                    // the join it implies; this target does not do joins yet,
                    // and writing a column nothing reads would be worse.
                    continue;
                }

                $columns[] = [
                    'name' => self::slugify((string) $field->field_name),
                    'type' => self::COLUMN_TYPES[(string) (FieldKind::property($field)->type ?? '')] ?? 'longtext',
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
     * The manifest values a plugin header needs, with usable fallbacks.
     *
     * A WordPress plugin header with an empty Version is a plugin WordPress
     * lists as broken, so nothing here may come out blank.
     *
     * @return array<string, string>
     *
     * @since  1.4.0
     */
    protected function header(): array
    {
        $manifest = $this->project->manifest();

        return [
            'pluginName'  => $this->pluginName(),
            'slug'        => $this->slug(),
            'prefix'      => $this->classPrefix(),
            'constant'    => $this->constantPrefix(),
            'version'     => trim((string) ($manifest->version ?? '')) ?: '1.0.0',
            'license'     => trim((string) ($manifest->license ?? '')) ?: 'GPL-2.0-or-later',
            'authorName'  => trim((string) ($manifest->author_name ?? '')) ?: 'Unknown',
            'authorUrl'   => trim((string) ($manifest->author_url ?? '')) ?: '',
            'description' => trim((string) ($this->project->component()->component_description ?? ''))
                ?: $this->pluginName() . '.',
        ];
    }
}
