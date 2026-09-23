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
     * Which metalanguage this project says it is written in.
     *
     * The binding 3.4 gave a project, read straight off the row rather than
     * through `ProjectModel`: the generate screen needs it before it runs
     * anything, and it has an id and a database and no item.
     *
     * A row that is missing returns null, and so does one whose binding is
     * empty - which no row has had since the install script filled them in, and
     * which means "this project does not say" rather than "this project is
     * written in the default". There is no default; that is what 3.4 was for.
     *
     * @return  ?array{key: string, version: string}
     *
     * @since   1.4.0
     */
    public function binding(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['metalanguage_key', 'metalanguage_version']))
            ->from($this->db->quoteName('#__extengen_projects'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $row = $this->db->loadObject();

        if ($row === null) {
            return null;
        }

        $key = trim((string) ($row->metalanguage_key ?? ''));

        if ($key === '') {
            return null;
        }

        return ['key' => $key, 'version' => trim((string) ($row->metalanguage_version ?? ''))];
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
