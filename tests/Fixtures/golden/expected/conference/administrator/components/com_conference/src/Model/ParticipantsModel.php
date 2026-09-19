<?php
/**
 * @package    MyConference
 * @subpackage Conference
 * @version    1.0.0
 *
 * @copyright  Herman Peeren - Yepr - 2023
 * @license    GPL 3.0
 */

namespace Yepr\Component\Conference\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Participants records.
 */
class ParticipantsModel extends ListModel
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
							'name','speaker.name',
							'organisation','speaker.organisation',
							'id','speaker.id'
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
			$db->quoteName('speaker.name'),
			$db->quoteName('speaker.organisation'),
			$db->quoteName('speaker.nationality'),
			$db->quoteName('speaker.address'),
			$db->quoteName('speaker.id')
		]);
		$query->from($db->quoteName('#__conference_speaker', 'speaker'));

		// Add filters to query
		// Get the value of name filter here
		$name = $this->getState('filter.name');
		if (!empty($name))
		{
			$query
				->where($db->quoteName('speaker.name') . ' LIKE :name')
				->bind(':name', $name, ParameterType::STRING);
		}
		// Get the value of organisation filter here
		$organisation = $this->getState('filter.organisation');
		if (!empty($organisation))
		{
			$query
				->where($db->quoteName('speaker.organisation') . ' LIKE :organisation')
				->bind(':organisation', $organisation, ParameterType::STRING);
		}
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
