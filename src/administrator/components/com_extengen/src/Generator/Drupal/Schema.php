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

/**
 * A table per entity, described rather than created.
 *
 * **The third answer this repository has to "what is a schema", and the one
 * that settles the question.** Joomla writes `sql/install.mysql.utf8.sql`, runs
 * it once and records the version in `#__schemas`. WordPress writes a
 * `dbDelta()` call in an activation hook, and re-runs it on every activation so
 * the same statement is also every upgrade. Drupal writes neither: `hook_schema()`
 * returns a PHP array *describing* the tables, and Drupal builds the statements
 * itself for whichever driver the site runs on.
 *
 * Two targets could have been a coincidence - one file format against another.
 * Three is the shape of the thing: a schema is not a file, a statement or a
 * description, it is whichever of those the target says, and the only thing all
 * three share is the columns. Which is what an ER1 model holds, and the reason
 * this generator is thirty lines.
 *
 * @since  1.4.0
 */
final class Schema extends DrupalGenerator
{
    /**
     * @return  string[]  A log of what was produced.
     *
     * @since   1.4.0
     */
    protected function generateFiles(): array
    {
        return $this->generateFileWithTemplate(
            'module/',
            'install.twig',
            $this->moduleRoot(),
            $this->machineName() . '.install',
            $this->header() + ['entities' => $this->entities()]
        );
    }
}
