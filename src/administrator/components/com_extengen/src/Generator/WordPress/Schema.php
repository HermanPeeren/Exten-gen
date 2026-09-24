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
 * A table per entity, created the way WordPress creates tables.
 *
 * Not a `.sql` file, which is what the Joomla target writes and what an ER1
 * model most obviously suggests. WordPress has no schema installer: a plugin
 * calls `dbDelta()` from its activation hook with a CREATE TABLE statement, and
 * dbDelta compares that against the database and *alters* - so the same
 * statement is both the install and every upgrade after it.
 *
 * **This is the difference worth pointing at**, because it is the one a target
 * abstraction has to survive. The two CMSs do not disagree about the columns;
 * they disagree about what a schema *is* - a file Joomla runs once and records
 * in `#__schemas`, against a statement WordPress re-runs on every activation
 * and diffs. A generator that had assumed "schema means a .sql file" would have
 * had that assumption somewhere above the target, and 4.4 would have found it.
 *
 * dbDelta's parser is famously literal: one field per line, two spaces after
 * PRIMARY KEY, and the key inline rather than as a separate statement. That is
 * in the template, where it is visible.
 *
 * @since  1.4.0
 */
final class Schema extends WordPressGenerator
{
    /**
     * @return  string[]  A log of what was produced.
     *
     * @since   1.4.0
     */
    protected function generateFiles(): array
    {
        return $this->generateFileWithTemplate(
            'plugin/',
            'activator.php.twig',
            $this->pluginRoot() . 'includes/',
            'class-' . $this->slug() . '-activator.php',
            $this->header() + ['entities' => $this->entities()]
        );
    }
}
