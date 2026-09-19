<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Generator\Rules;

use Yepr\Gen\Core\Rule\Registry;

/**
 * Which source elements a rule can be written for.
 *
 * Four, and that is the whole list: the project, its entities, the pages its
 * component puts in the back end, and the pages it puts in the front end. Every
 * file a Joomla 6 component needs is produced once per one of those.
 *
 * Two of the four are joins rather than lists. `pages` in the model is a flat
 * collection; which of them are back-end pages is recorded somewhere else
 * entirely, as references under `Sections`. The imperative generators did that
 * join inline, twice, with a `$pageMap` built at the top of each - which is why
 * "the back-end pages" was not a thing you could name, only a thing you could
 * re-derive. Naming it is the point.
 *
 * A selector yields nodes in model order, and that order reaches the output:
 * the first back-end index page becomes the component's default view.
 *
 * @since  1.1.0
 */
final class Joomla6Selectors
{
    /**
     * The selectors, ready for the engine.
     *
     * @return  Registry
     *
     * @since   1.1.0
     */
    public static function registry(): Registry
    {
        return (new Registry('selector'))
            ->register('root', static fn (object $model): array => [$model])
            ->register('entities', static fn (object $model): array => (array) ($model->datamodel ?? []))
            ->register('backendPages', static fn (object $model): array => self::pages($model, 'backendsection'))
            ->register('frontendPages', static fn (object $model): array => self::pages($model, 'frontendsection'));
    }

    /**
     * The pages one section of the component points at, in the order it lists them.
     *
     * A reference to a page that does not exist is skipped rather than fatal.
     * The imperative version indexed `$pageMap[$ref->page_reference]` straight
     * out, so a model with a dangling reference - which the forms can produce
     * by deleting a page that a section still names - stopped generation with
     * an undefined-index error and no indication of which page it meant.
     *
     * @param   object  $model    The decoded project.
     * @param   string  $section  `backendsection` or `frontendsection`.
     *
     * @return  object[]
     *
     * @since   1.1.0
     */
    public static function pages(object $model, string $section): array
    {
        $sections = $model->extensions->component->Sections ?? null;

        if ($sections === null || !property_exists($sections, $section)) {
            return [];
        }

        $byId = [];

        foreach ((array) ($model->pages ?? []) as $page) {
            $byId[$page->page_id] = $page;
        }

        $pages = [];

        foreach ((array) $sections->{$section} as $reference) {
            $id = $reference->page_reference ?? null;

            if ($id !== null && isset($byId[$id])) {
                $pages[] = $byId[$id];
            }
        }

        return $pages;
    }
}
