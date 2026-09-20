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

use Yepr\Gen\Core\Rule\PathReader;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Everything in a stored model that something else can point at.
 *
 * A reference in the model is an identifier. A person choosing one needs a
 * name, so every reference dropdown needs the same thing: for each kind of
 * object, the id and name of each one in the model.
 *
 * Until 1.9 each dropdown worked that out for itself. Three field classes -
 * `EntityReferenceField`, `FieldReferenceField`, `PageReferenceField` - each
 * loaded the whole project from the database and walked it, so a form with
 * twenty reference fields ran twenty queries for one project, and the answer
 * was baked into `<option>` tags at render time. This computes it once and the
 * page carries it, which is what makes the client able to answer the same
 * question about objects that are not in the database yet.
 *
 * **Two models, one mechanism.** A project is modelled in ER1 - entities,
 * fields, pages. A projectForm is modelled in LionCore M3 - language entities
 * that are classifiers or datatypes, and classifiers that are concepts, concept
 * interfaces or annotations. The two share nothing but the shape of the
 * question, so what differs between them is a table and nothing else. Until 3.1
 * the M3 dropdowns had six field classes of their own, each loading the stored
 * projectForm from the database - so they described what had been saved rather
 * than what was on screen, which is the defect 1.9 fixed for ER1 and left here.
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
     * What a project offers, in ER1.
     *
     * A map rather than a method per type, because Meta-gen generates this from
     * a concept model in stage 3 and a list is a thing that can be generated.
     * `parentKey` names the field on the child that holds its parent's id.
     *
     * @var array<string, array<string, mixed>>
     *
     * @since  1.0.0
     */
    private const PROJECT = [
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
     * What a projectForm offers, in LionCore M3.
     *
     * Every type lives in the same place - `languageEntities` - and they differ
     * only by what the row says it is. So this table needs one thing the ER1
     * table does not: a condition.
     *
     * It is written twice, once as a path through the stored model and once as
     * a token in an element id, for the same reason `idKey` and `idToken` are
     * two entries. The server reads `classifier.classifier_type` out of JSON;
     * the browser reads the same value out of an input whose element id ends
     * `classifier__classifier_type`. Neither spelling can be derived from the
     * other without knowing how Joomla builds element ids, so both are written
     * down in the one place that describes an object type.
     *
     * @var array<string, array<string, mixed>>
     *
     * @since  1.1.0
     */
    private const PROJECT_FORM = [
        'Classifier' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [['path' => 'languageEntity_type', 'value' => 'Classifier']],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [['token' => 'languageEntity_type', 'value' => 'Classifier']],
            ],
        ],
        'Concept' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [
                ['path' => 'languageEntity_type', 'value' => 'Classifier'],
                ['path' => 'classifier.classifier_type', 'value' => 'Concept'],
            ],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [
                    ['token' => 'languageEntity_type', 'value' => 'Classifier'],
                    ['token' => 'classifier__classifier_type', 'value' => 'Concept'],
                ],
            ],
        ],
        'ConceptInterface' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [
                ['path' => 'languageEntity_type', 'value' => 'Classifier'],
                ['path' => 'classifier.classifier_type', 'value' => 'ConceptInterface'],
            ],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [
                    ['token' => 'languageEntity_type', 'value' => 'Classifier'],
                    ['token' => 'classifier__classifier_type', 'value' => 'ConceptInterface'],
                ],
            ],
        ],
        'Annotation' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [
                ['path' => 'languageEntity_type', 'value' => 'Classifier'],
                ['path' => 'classifier.classifier_type', 'value' => 'Annotation'],
            ],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [
                    ['token' => 'languageEntity_type', 'value' => 'Classifier'],
                    ['token' => 'classifier__classifier_type', 'value' => 'Annotation'],
                ],
            ],
        ],
        'DataType' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [['path' => 'languageEntity_type', 'value' => 'DataType']],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [['token' => 'languageEntity_type', 'value' => 'DataType']],
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param  array<string, array<string, mixed>>  $types  What this kind of model offers.
     *
     * @since  1.1.0
     */
    private function __construct(private readonly array $types)
    {
    }

    /**
     * The index of a project, in ER1.
     *
     * @since  1.1.0
     */
    public static function project(): self
    {
        return new self(self::PROJECT);
    }

    /**
     * The index of a language whose table was generated from its concept model.
     *
     * The two tables above are written by hand because the two languages they
     * describe are. A language somebody models gets its table from
     * `Generator\Meta\ReferenceTable`, which reads the same concept model the
     * forms are generated from - so the dropdowns and the forms cannot describe
     * different things, which two hand-written halves always eventually do.
     *
     * Nothing is checked here beyond the shape being a map, because the table
     * arrives from the generator rather than from a person: `index()` and
     * `clientTypes()` read exactly the keys the generator writes, and a table
     * missing one of them is a defect in the generator that its own tests are
     * where to catch.
     *
     * @param  array<string, array<string, mixed>>  $types  A generated table.
     *
     * @since  1.2.0
     */
    public static function fromTable(array $types): self
    {
        return new self($types);
    }

    /**
     * The index of a projectForm, in LionCore M3.
     *
     * @since  1.1.0
     */
    public static function projectForm(): self
    {
        return new self(self::PROJECT_FORM);
    }

    /**
     * Everything one stored model offers, by type.
     *
     * @param  ?object  $model  The model as stored, or null for one that has
     *                          never been saved.
     *
     * @return array<string, list<array{id: string, name: string, parent?: string}>>
     *
     * @since  1.0.0
     */
    public function index(?object $model): array
    {
        $index = [];

        foreach ($this->types as $type => $definition) {
            $index[$type] = [];

            foreach ($this->at($model, $definition['path']) as $node) {
                if (!$this->matches($node, $definition['when'] ?? [])) {
                    continue;
                }

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
     * "look for entities": it needs the class on the name input, how that
     * input's element id relates to the hidden id beside it, and - for a type
     * that is one kind of a shared row - which other input in the row decides
     * whether it counts.
     *
     * @return array<string, array<string, mixed>>
     *
     * @since  1.0.0
     */
    public function clientTypes(): array
    {
        return array_map(static fn (array $definition): array => $definition['client'], $this->types);
    }

    /**
     * Whether a row is of the type being indexed.
     *
     * @param  array<int, array<string, string>>  $conditions
     *
     * @since  1.1.0
     */
    private function matches(object $node, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ((string) PathReader::read($node, $condition['path']) !== $condition['value']) {
                return false;
            }
        }

        return true;
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
     * A non-repeating subform is stored as the group itself rather than as one
     * keyed row - `SubformField::filter()` branches on `multiple` - which is
     * why `classifier.classifier_type` above is a plain dotted read and not
     * another step here.
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
