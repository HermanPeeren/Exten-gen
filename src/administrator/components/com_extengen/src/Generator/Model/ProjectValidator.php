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

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }
}
