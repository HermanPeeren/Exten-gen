<?php
/**
 * @package    Extengen
 * @subpackage Extengen
 * @version    1.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL 3.0
 */

namespace Yepr\Component\Extengen\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Projects records.
 */
class ProjectsModel extends ListModel
{
    /**
	 * Constructor.
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 *
	 * @see     \JControllerLegacy
	 */
	public function __construct($config = [])
	{
		// Add filter fields
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
							'name','project.name',
							'metalanguage_key','project.metalanguage_key',
							'id','project.id'
			];

			// Todo: Add fields for standard Joomla filtering, like categories, language, published, ordering etc.
			// Todo: Those standard  Joomla features have to be added to the AST first.
			// Todo: Association filter field, if ($assoc)
		}

		parent::__construct($config);
	}


	/**
	 * Build an SQL query to load the list data.
	 *
	 * @return  QueryInterface
	 */
	protected function getListQuery():QueryInterface
	{
		// Create a new query object.
		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select([
			$db->quoteName('project.name'),
			$db->quoteName('project.form_data'),
			$db->quoteName('project.metalanguage_key'),
			$db->quoteName('project.metalanguage_version'),
			$db->quoteName('project.id')
		]);
		$query->from($db->quoteName('#__extengen_project', 'project'));

		// Add filters to query
		// Get the value of name filter here
		$name = $this->getState('filter.name');
		if (!empty($name))
		{
			$query
				->where($db->quoteName('project.name') . ' LIKE :name')
				->bind(':name', $name, ParameterType::STRING);
		}
		// Get the value of metalanguage_key filter here
		$metalanguage_key = $this->getState('filter.metalanguage_key');
		if (!empty($metalanguage_key))
		{
			$query
				->where($db->quoteName('project.metalanguage_key') . ' LIKE :metalanguage_key')
				->bind(':metalanguage_key', $metalanguage_key, ParameterType::STRING);
		}
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
