<?php

/**
 * @package     Extengen
 * @subpackage  Reference
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Reference;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a project offers a reference dropdown, in ER1.
 *
 * The mechanism that reads this is `Yepr\Gen\Core\Reference\ReferenceIndex` in
 * the shared library. It used to be here, carrying two tables as constants -
 * this one and LionCore M3's - and saying in its own docblock that they were a
 * map rather than a method per type *because Meta-gen generates this from a
 * concept model*. Meta-gen does now, and Meta-gen is its own component, so what
 * is shared is the mechanism and what stays is the table.
 *
 * **ER1 is the opposite shape to a discriminated language.** Entities, pages
 * and fields live in three different repeating groups and nothing has to say
 * what it is, so every path differs and no entry needs a condition. A field is
 * the one scoped type: it carries which entity it belongs to, so a dropdown
 * offering "the fields of this entity" can filter before anything is saved.
 *
 * **This is hand-written, and 3.3 is where it stops being.** Modelling ER1 in
 * LionCore M3 and generating this table from it is that step's acceptance
 * criterion, and Meta-gen already reproduces this table from a language shaped
 * like ER1 - `MetaReferenceTableTest` over there is what says so.
 *
 * @since  1.2.0
 */
final class Er1
{
    /**
     * The table, as `ReferenceIndex::fromTable()` reads one.
     *
     * @var array<string, array<string, mixed>>
     *
     * @since  1.2.0
     */
    public const TABLE = [
        'Entity' => [
            'path'    => ['datamodel'],
            'idKey'   => 'entity_id',
            'nameKey' => 'entity_name',
            'client'  => [
                'selector'  => 'entityName',
                'nameToken' => 'entity_name',
                'idToken'   => 'entity_id',
            ],
        ],
        'Page' => [
            'path'    => ['pages'],
            'idKey'   => 'page_id',
            'nameKey' => 'page_name',
            'client'  => [
                'selector'  => 'pageName',
                'nameToken' => 'page_name',
                'idToken'   => 'page_id',
            ],
        ],
        'Field' => [
            'path'      => ['datamodel', 'field'],
            'idKey'     => 'field_id',
            'nameKey'   => 'field_name',
            'parentKey' => 'entity_id',
            'client'    => [
                'selector'    => 'fieldName',
                'nameToken'   => 'field_name',
                'idToken'     => 'field_id',
                'parentToken' => 'entity_id',
                // A field's element id is its entity's with the repeating group
                // appended, so cutting here reaches the entity's own id input.
                // That is how a field added a moment ago, whose entity_id is
                // still blank, finds out which entity it belongs to.
                'parentCut'   => '_field__field',
            ],
        ],
    ];
}
