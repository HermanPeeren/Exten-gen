<?php

/**
 * @package     Extengen
 * @subpackage  Repository
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Repository;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;

/**
 * Loads a stored project.
 *
 * The one place that knows a project lives in a database table called
 * `#__extengen_projects` in a column called `form_data`. Before this there were
 * five copies of that knowledge among the field classes and the models, and a
 * sixth in a method with a different name that did the same thing.
 *
 * Invalid JSON returns null rather than throwing. A reference field asking
 * "which entities can this point at" while the user is editing a broken model
 * should offer an empty list, not take down the page the user needs in order to
 * fix it. Generation is where a bad model has to be refused, and that is the
 * validator's job.
 *
 * @since  0.9.0
 */
final class ProjectRepository
{
    /**
     * @param  DatabaseInterface  $db  The database to read from.
     *
     * @since  0.9.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * The stored project with this id, or null when there is none to read.
     *
     * @since  0.9.0
     */
    public function find(int $id): ?Project
    {
        $json = $this->formData($id);

        if ($json === null) {
            return null;
        }

        try {
            return Project::fromJson($json);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * The raw stored JSON, or null when the row is missing or empty.
     *
     * @since  0.9.0
     */
    private function formData(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('form_data'))
            ->from($this->db->quoteName('#__extengen_projects'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $json = $this->db->loadResult();

        return \is_string($json) && trim($json) !== '' ? $json : null;
    }
}
