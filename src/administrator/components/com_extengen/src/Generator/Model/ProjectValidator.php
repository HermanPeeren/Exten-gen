<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Model;

use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Model\ValidationException;
use Yepr\Gen\Core\Model\ValidatorInterface;

/**
 * What a project must contain before anything is generated from it.
 *
 * Every problem is collected rather than only the first, because a user fixing
 * a model one error per run is a user who gives up.
 *
 * Deliberately thin for now. It checks what the generators would otherwise fail
 * on with a PHP error somewhere deep in a template - a missing component name
 * produces files called `com_.php`, a project with no entities produces a
 * component with no tables - and nothing more. Rules that belong to a particular
 * output go in that target's own validator once generation moves onto the
 * pipeline at step 1.4.
 *
 * @since  0.9.0
 */
final class ProjectValidator implements ValidatorInterface
{
    /**
     * The versions of the stored format this code understands.
     *
     * A version it has never heard of is refused rather than read hopefully:
     * misreading a newer model is how a generator writes plausible nonsense.
     *
     * @var string[]
     *
     * @since  0.9.0
     */
    private const SUPPORTED_VERSIONS = ['1.0'];

    /**
     * Throw unless the project can be generated from.
     *
     * @throws ValidationException  When it cannot.
     *
     * @since  0.9.0
     */
    public function assertValid(ModelInterface $model): void
    {
        if (!$model instanceof Project) {
            throw new ValidationException([
                'expected a project, got ' . get_debug_type($model),
            ]);
        }

        $errors = [];

        if (!\in_array($model->modelVersion(), self::SUPPORTED_VERSIONS, true)) {
            $errors[] = \sprintf(
                'the project was stored in format version %s, and this version of Exten-gen reads %s',
                $model->modelVersion(),
                implode(', ', self::SUPPORTED_VERSIONS)
            );
        }

        if (trim($model->componentName()) === '') {
            $errors[] = 'the extension has no component name';
        }

        if ($model->entities() === []) {
            $errors[] = 'the project has no entities, so there would be nothing to store';
        }

        if ($model->pages() === []) {
            $errors[] = 'the project has no pages, so there would be nothing to show';
        }

        foreach ($model->entities() as $index => $entity) {
            if (trim((string) ($entity->entity_name ?? '')) === '') {
                $errors[] = \sprintf('entity %d has no name', $index + 1);
            }
        }

        foreach ($model->pages() as $index => $page) {
            if (trim((string) ($page->page_name ?? '')) === '') {
                $errors[] = \sprintf('page %d has no name', $index + 1);
            }
        }

        $errors = [...$errors, ...$this->duplicates($model)];

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Anything a repeating group names twice.
     *
     * Every one of these means the same file, table or column is produced
     * twice, and the second one wins. That used to be invisible: the generator
     * opened each file with `fopen(..., 'w')`, and a second write to the same
     * path is just a write. Two real stored models turned out to contain one -
     * a page listed twice among the back-end pages, and a language listed twice
     * - and neither had ever been noticed.
     *
     * The rule is about repeating groups rather than about pages or languages
     * in particular, because that is the shape of the mistake: a subform where
     * the same entry was added twice. Both known cases are instances of it, and
     * so is the next one.
     *
     * @return string[]
     *
     * @since  0.9.0
     */
    private function duplicates(Project $model): array
    {
        $errors = [];

        $groups = [
            'entity'   => array_map(
                static fn (object $e): string => trim((string) ($e->entity_name ?? '')),
                $model->entities()
            ),
            'page'     => array_map(
                static fn (object $p): string => trim((string) ($p->page_name ?? '')),
                $model->pages()
            ),
            'language' => array_map(
                static fn (object $l): string => trim((string) ($l->language_code ?? ''))
                    . '-' . trim((string) ($l->country_code ?? '')),
                $model->languages()
            ),
        ];

        // A section holds references, and a reference is a uuid. Reported as
        // one, it tells the reader nothing they can act on, so it is resolved
        // back to the page name they chose in the form.
        $pageNames = [];

        foreach ($model->pages() as $page) {
            $pageNames[(string) ($page->page_id ?? '')] = trim((string) ($page->page_name ?? ''));
        }

        $labels = ['backendsection' => 'back-end page', 'frontendsection' => 'front-end page'];

        foreach ($model->sections() as $section => $references) {
            $groups[$labels[$section] ?? $section] = array_map(
                static function (object $reference) use ($pageNames): string {
                    $id = (string) ($reference->page_reference ?? '');

                    return $pageNames[$id] ?? $id;
                },
                $references
            );
        }

        foreach ($groups as $what => $names) {
            foreach ($this->repeated($names) as $name => $count) {
                $errors[] = \sprintf(
                    'the %s list names "%s" %d times; each entry has to be distinct',
                    $what,
                    $name,
                    $count
                );
            }
        }

        // Fields belong to their entity, so the same name in two entities is
        // two different columns and perfectly fine.
        foreach ($model->entities() as $entity) {
            $fields = \is_object($entity->field ?? null) ? get_object_vars($entity->field) : (array) ($entity->field ?? []);

            $names = array_map(
                static fn (mixed $f): string => \is_object($f) ? strtolower(trim((string) ($f->field_name ?? ''))) : '',
                array_values($fields)
            );

            foreach ($this->repeated($names) as $name => $count) {
                $errors[] = \sprintf(
                    'entity "%s" names the field "%s" %d times',
                    (string) ($entity->entity_name ?? '?'),
                    $name,
                    $count
                );
            }
        }

        // A slot is one place in one generated file. Two bodies aimed at it
        // means one of them is emitted and the other is not, and the model
        // gives no hint which - the last one wins only because it was stored
        // later.
        $owners = [];

        foreach ($model->entities() as $entity) {
            $owners['entity "' . (string) ($entity->entity_name ?? '?') . '"'] = $entity;
        }

        foreach ($model->pages() as $page) {
            $owners['page "' . (string) ($page->page_name ?? '?') . '"'] = $page;
        }

        foreach ($owners as $what => $node) {
            $entries = \is_object($node->customcode ?? null) ? get_object_vars($node->customcode) : [];

            $slots = array_map(
                static fn (mixed $e): string => \is_object($e) ? trim((string) ($e->slot ?? '')) : '',
                array_values($entries)
            );

            foreach ($this->repeated($slots) as $slot => $count) {
                $errors[] = \sprintf(
                    '%s has %d pieces of custom code for the slot "%s"; a slot holds one',
                    $what,
                    $count,
                    $slot
                );
            }
        }

        return $errors;
    }

    /**
     * The values that occur more than once, ignoring empty ones.
     *
     * An empty name is somebody else's error - the checks above report it - and
     * reporting it twice helps nobody.
     *
     * @param  string[]  $values
     *
     * @return array<string, int>  value => how many times
     *
     * @since  0.9.0
     */
    private function repeated(array $values): array
    {
        $counts = [];
        $seen   = [];

        foreach ($values as $value) {
            if ($value === '' || $value === '-') {
                continue;
            }

            // Compared without regard to case, because two entities called
            // Flight and flight are one table; reported with the case the user
            // typed, because that is what they will be looking for.
            $key = strtolower($value);

            $seen[$key] ??= $value;
            $counts[$seen[$key]] = ($counts[$seen[$key]] ?? 0) + 1;
        }

        return array_filter($counts, static fn (int $count): bool => $count > 1);
    }
}
