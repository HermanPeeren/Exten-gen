<?php

/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Utilities\ArrayHelper;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogueAwareInterface;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogueAwareTrait;
use Yepr\Component\Extengen\Administrator\Generators\PackageLocation;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepositoryAwareInterface;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepositoryAwareTrait;

/**
 * Model with methods supporting a list of projects.
 *
 * The generator catalogue and the project repository are handed over by the
 * component's MVC factory, for the packages column.
 */
class ProjectsModel extends ListModel implements GeneratorCatalogueAwareInterface, ProjectRepositoryAwareInterface
{
	use GeneratorCatalogueAwareTrait;
	use ProjectRepositoryAwareTrait;

	/**
	 * The packages that have been generated for the projects on this page.
	 *
	 * One per generator that has written a package for a project's current
	 * version - the one `generate.download` hands out, so every link in the
	 * list is one the download task will honour. Looked for on disk rather
	 * than recorded anywhere, because the folder is the only record there is:
	 * a package removed by hand is not offered.
	 *
	 * @param   object[]  $items  The rows on the page.
	 * @param   ?string   $root   Where output goes; the administrator's when null.
	 *
	 * @return  array<int, array<int, array{generator: string, label: string, file: string}>>  By project id.
	 *
	 * @since   1.3.4
	 */
	public function packages(array $items, ?string $root = null): array
	{
		$root       = $root ?? JPATH_ROOT . '/' . PackageLocation::ADMIN_ROOT;
		$generators = $this->getGeneratorCatalogue()->all();
		$packages   = [];

		foreach ($items as $item) {
			$id      = (int) ($item->id ?? 0);
			$project = $id > 0 ? $this->getProjectRepository()->find($id) : null;

			if ($project === null || $project->componentName() === '') {
				continue;
			}

			foreach ($generators as $generator) {
				$archive = PackageLocation::archive($root, $id, $project, $generator->id);

				if (is_file($archive)) {
					$packages[$id][] = [
						'generator' => $generator->id,
						'label'     => $generator->label(),
						'file'      => basename($archive),
					];
				}
			}
		}

		return $packages;
	}

	/**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @return  void
	 */
	public function __construct($config = array())
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
				'id', 'a.id',
				'name', 'a.name',
				'alias', 'a.alias',
				'catid', 'a.catid', 'category_id', 'category_title',
				'checked_out', 'a.checked_out',
				'checked_out_time', 'a.checked_out_time',
				'published', 'a.published',
				'access', 'a.access', 'access_level',
				'ordering', 'a.ordering',
				'language', 'a.language', 'language_title',
				'publish_up', 'a.publish_up',
				'publish_down', 'a.publish_down',
				'metalanguage', 'metalanguage_name',
			);

			$assoc = Associations::isEnabled();

			if ($assoc) {
				$config['filter_fields'][] = 'association';
			}
		}

		parent::__construct($config);
	}

	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  \Joomla\Database\QueryInterface
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select(
			$db->quoteName(
				explode(
					', ',
					$this->getState(
						'list.select',
						'a.id, a.name, a.catid' .
						', a.access' .
						', a.checked_out' .
						', a.checked_out_time' .
						', a.language' .
						', a.ordering' .
						', a.state' .
						', a.published' .
						', a.publish_up, a.publish_down' .
						', a.metalanguage_key, a.metalanguage_version'
					)
				)
			)
		);

		$query->from($db->quoteName('#__extengen_projects', 'a'));

		// Join over the asset groups.
		$query->select($db->quoteName('ag.title', 'access_level'))
			->join(
				'LEFT',
				$db->quoteName('#__viewlevels', 'ag') . ' ON ' . $db->quoteName('ag.id') . ' = ' . $db->quoteName('a.access')
			);

		// Join over the categories.
		$query->select($db->quoteName('c.title', 'category_title'))
			->join(
				'LEFT',
				$db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid')
			);

		// Join over the language
		$query->select($db->quoteName('l.title', 'language_title'))
			->select($db->quoteName('l.image', 'language_image'))
			->join(
				'LEFT',
				$db->quoteName('#__languages', 'l') . ' ON ' . $db->quoteName('l.lang_code') . ' = ' . $db->quoteName('a.language')
			);

		// Join over the associations.
		if (Associations::isEnabled()) {
			$subQuery = $db->getQuery(true)
				->select('COUNT(' . $db->quoteName('asso1.id') . ') > 1')
				->from($db->quoteName('#__associations', 'asso1'))
				->join('INNER', $db->quoteName('#__associations', 'asso2'), $db->quoteName('asso1.key') . ' = ' . $db->quoteName('asso2.key'))
				->where(
					[
						$db->quoteName('asso1.id') . ' = ' . $db->quoteName('a.id'),
						$db->quoteName('asso1.context') . ' = ' . $db->quote('COM_EXTENGEN.item'),
					]
				);

			$query->select('(' . $subQuery . ') AS ' . $db->quoteName('association'));
		}

		// Join over the users for the checked out user.
		// The language each project is written in, by name: step 5.3. A left
		// join, because a project whose language this site has lost is still a
		// row in the list - it shows its stored key instead.
		$query->select($db->quoteName('m.name', 'metalanguage_name'))
			->join(
				'LEFT',
				$db->quoteName('#__extengen_metalanguages', 'm') . ' ON ' . $db->quoteName('m.lang_key') . ' = ' . $db->quoteName('a.metalanguage_key')
				. ' AND ' . $db->quoteName('m.version') . ' = ' . $db->quoteName('a.metalanguage_version')
			);

		$query->select($db->quoteName('uc.name', 'editor'))
			->join(
				'LEFT',
				$db->quoteName('#__users', 'uc') . ' ON ' . $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out')
			);

		// Filter by access level.
		if ($access = $this->getState('filter.access')) {
			$query->where($db->quoteName('a.access') . ' = ' . (int) $access);
		}

		// Filter by published state
		$published = (string) $this->getState('filter.published');

		if (is_numeric($published)) {
			$query->where($db->quoteName('a.published') . ' = ' . (int) $published);
		} elseif ($published === '') {
			$query->where('(' . $db->quoteName('a.published') . ' = 0 OR ' . $db->quoteName('a.published') . ' = 1)');
		}

		// Filter by a single or group of categories.
		$categoryId = $this->getState('filter.category_id');

		if (is_numeric($categoryId)) {
			$query->where($db->quoteName('a.catid') . ' = ' . (int) $categoryId);
		} elseif (is_array($categoryId)) {
			$query->where($db->quoteName('a.catid') . ' IN (' . implode(',', ArrayHelper::toInteger($categoryId)) . ')');
		}

		// Filter by search in name.
		$search = $this->getState('filter.search');

		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = ' . (int) substr($search, 3));
			} else {
				$search = $db->quote('%' . str_replace(' ', '%', $db->escape(trim($search), true) . '%'));
				$query->where(
					'(' . $db->quoteName('a.name') . ' LIKE ' . $search . ')'
				);
			}
		}

		// Filter on the language.
		// `key|version`, the value MetalanguageField posts. A key alone is
		// accepted too, meaning every version of that language.
		$metalanguage = (string) $this->getState('filter.metalanguage');

		if ($metalanguage !== '') {
			[$key, $version] = array_pad(explode('|', $metalanguage, 2), 2, '');

			$query->where($db->quoteName('a.metalanguage_key') . ' = :mlkey')
				->bind(':mlkey', $key);

			if ($version !== '') {
				$query->where($db->quoteName('a.metalanguage_version') . ' = :mlversion')
					->bind(':mlversion', $version);
			}
		}

		if ($language = $this->getState('filter.language')) {
			$query->where($db->quoteName('a.language') . ' = ' . $db->quote($language));
		}

		// Add the list ordering clause.
		$orderCol = $this->state->get('list.ordering', 'a.name');
		$orderDirn = $this->state->get('list.direction', 'asc');

		if ($orderCol === 'metalanguage_name') {
			$orderCol = $db->quoteName('m.name') . ' ' . $orderDirn . ', ' . $db->quoteName('a.metalanguage_version');
		}

		if ($orderCol == 'a.ordering' || $orderCol == 'category_title') {
			$orderCol = $db->quoteName('c.title') . ' ' . $orderDirn . ', ' . $db->quoteName('a.ordering');
		}

		$query->order($db->escape($orderCol . ' ' . $orderDirn));

		return $query;
	}

	/**
	 * Method to auto-populate the model state.
	 *
	 * Note. Calling getState in this method will result in recursion.
	 *
	 * @param   string  $ordering   An optional ordering field.
	 * @param   string  $direction  An optional direction (asc|desc).
	 *
	 * @return  void
	 */
	protected function populateState($ordering = 'a.name', $direction = 'asc')
	{
		$app = Factory::getApplication();

		$forcedLanguage = $app->getInput()->get('forcedLanguage', '', 'cmd');

		// Adjust the context to support modal layouts.
		if ($layout = $app->getInput()->get('layout')) {
			$this->context .= '.' . $layout;
		}

		// Adjust the context to support forced languages.
		if ($forcedLanguage) {
			$this->context .= '.' . $forcedLanguage;
		}

		// List state information.
		parent::populateState($ordering, $direction);

		// Force a language.
		if (!empty($forcedLanguage)) {
			$this->setState('filter.language', $forcedLanguage);
		}
	}
}
