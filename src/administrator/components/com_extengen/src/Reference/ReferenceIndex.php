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
 * Everything in a stored project that something else can point at.
 *
 * A reference in the model is a uuid. A person choosing one needs a name, so
 * every reference dropdown needs the same thing: for each kind of object, the
 * id and name of each one in the project.
 *
 * Until 1.9 each dropdown worked that out for itself. Three field classes -
 * `EntityReferenceField`, `FieldReferenceField`, `PageReferenceField` - each
 * loaded the whole project from the database and walked it, so a form with
 * twenty reference fields ran twenty queries for one project, and the answer
 * was baked into `<option>` tags at render time. This computes it once and the
 * page carries it, which is what makes the client able to answer the same
 * question about objects that are not in the database yet.
 *
 * One flat list per object type, each entry `{id, name}`, and a child type also
 * carries `parent`. Uniform on purpose: a dropdown that is scoped to a parent -
 * the fields of one entity - filters, and a dropdown that is not, does not.
 * The alternative, nesting children under their parent, needs the reader to
 * know which types are nested before it can read the structure.
 *
 * @since  1.0.0
 */
final class ReferenceIndex
{
    /**
     * The object types this understands, and where they live in the model.
     *
     * A map rather than a method per type, because Meta-gen will generate this
     * from a concept model in stage 3 and a list is a thing that can be
     * generated. `parentKey` names the field on the child that holds its
     * parent's id.
     *
     * @var array<string, array{path: string[], idKey: string, nameKey: string, parentKey?: string}>
     *
     * @since  1.0.0
     */
    private const TYPES = [
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

    /**
     * The index for one stored project.
     *
     * @param  ?object  $project  The model as stored, or null for a project
     *                            that has never been saved.
     *
     * @return array<string, list<array{id: string, name: string, parent?: string}>>
     *
     * @since  1.0.0
     */
    public function forProject(?object $project): array
    {
        $index = [];

        foreach (self::TYPES as $type => $definition) {
            $index[$type] = [];

            foreach ($this->at($project, $definition['path']) as $node) {
                $id = $this->stringAt($node, $definition['idKey']);

                // An object with no id yet cannot be pointed at. That is not a
                // defect: the client assigns one the moment somebody names it.
                if ($id === '') {
                    continue;
                }

                $entry = ['id' => $id, 'name' => $this->stringAt($node, $definition['nameKey'])];

                if (isset($definition['parentKey'])) {
                    $entry['parent'] = $this->stringAt($node, $definition['parentKey']);
                }

                $index[$type][] = $entry;
            }
        }

        return $index;
    }

    /**
     * How the client finds each type's rows in the form.
     *
     * The same table, read from the other end. The browser cannot be told
     * "look for entities": it needs the class on the name input and how that
     * input's element id relates to the hidden id beside it. Keeping it here
     * means one description of an object type rather than one in PHP and
     * another in JavaScript that drifts from it - and in stage 3 Meta-gen
     * generates this table from a concept model, which it could not do if half
     * of it lived in a hand-written script.
     *
     * @return array<string, array<string, string>>
     *
     * @since  1.0.0
     */
    public function clientTypes(): array
    {
        return array_map(static fn (array $definition): array => $definition['client'], self::TYPES);
    }

    /**
     * Every node at a path through the model.
     *
     * Each step takes the object at that key and yields its values, because a
     * repeating group is stored as an object keyed `datamodel0`, `datamodel1`
     * and so on rather than as an array - that is what Joomla's subform field
     * hands back. So `['datamodel']` is every entity, and `['datamodel',
     * 'field']` is every field of every entity, with no step needed for
     * "each of the ones we just found".
     *
     * @param  string[]  $path
     *
     * @return list<object>
     *
     * @since  1.0.0
     */
    private function at(?object $node, array $path): array
    {
        if ($node === null) {
            return [];
        }

        $current = [$node];

        foreach ($path as $step) {
            $next = [];

            foreach ($current as $one) {
                if (!property_exists($one, $step) || !\is_object($one->{$step})) {
                    continue;
                }

                $next = array_merge($next, array_values((array) $one->{$step}));
            }

            $current = array_values(array_filter($next, \is_object(...)));
        }

        return $current;
    }

    /**
     * @since  1.0.0
     */
    private function stringAt(object $node, string $key): string
    {
        return property_exists($node, $key) && is_scalar($node->{$key}) ? (string) $node->{$key} : '';
    }
}
