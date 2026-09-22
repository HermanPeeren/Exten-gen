<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The metalanguages this site can write a project in: step 3.4.
 *
 * A plain model rather than a `ListModel`, and that is a decision rather than
 * a shortcut. `ListModel` brings pagination, filter state and an ordering
 * form, and every one of them assumes the list is a query over one table -
 * which this is not: ER1 is in it and is not a row anywhere. A site holds a
 * handful of languages, not a page of them.
 *
 * Reading also goes through the catalogue rather than through a query here,
 * so that what this screen shows and what the dropdown on a project offers
 * cannot come apart.
 *
 * @since  1.1.0
 */
class MetalanguagesModel extends BaseDatabaseModel
{
    /**
     * Every language, the built-in first.
     *
     * @return MetalanguageEntry[]
     *
     * @since  1.1.0
     */
    public function getItems(): array
    {
        return Metalanguages::catalogue($this->getDatabase())->all();
    }

    /**
     * How many projects are written in each language, keyed by binding.
     *
     * The screen needs it to say what removing one would break, and the count
     * for the built-in is every project that carries no binding at all -
     * which, before 3.4, is all of them.
     *
     * @return array<string, int>
     *
     * @since  1.1.0
     */
    public function projectCounts(): array
    {
        $database = $this->getDatabase();

        $query = $database->getQuery(true)
            ->select([
                $database->quoteName('metalanguage_key'),
                $database->quoteName('metalanguage_version'),
                'COUNT(*) AS ' . $database->quoteName('total'),
            ])
            ->from($database->quoteName('#__extengen_projects'))
            ->group($database->quoteName('metalanguage_key'))
            ->group($database->quoteName('metalanguage_version'));

        $database->setQuery($query);

        $counts = [];

        foreach ($database->loadObjectList() ?: [] as $row) {
            $key = (string) $row->metalanguage_key;

            $counts[$key === '' ? '' : $key . '|' . $row->metalanguage_version] = (int) $row->total;
        }

        return $counts;
    }
}
