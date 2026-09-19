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
 * Methods supporting a list of Rooms records.
 */
class RoomsModel extends ListModel
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
							'room_name','room.room_name',
							'position','room.position',
							'id','room.id'
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
			$db->quoteName('room.room_name'),
			$db->quoteName('room.position'),
			$db->quoteName('room.id')
		]);
		$query->from($db->quoteName('#__conference_room', 'room'));

		// Add filters to query
		// Get the value of room_name filter here
		$room_name = $this->getState('filter.room_name');
		if (!empty($room_name))
		{
			$query
				->where($db->quoteName('room.room_name') . ' LIKE :room_name')
				->bind(':room_name', $room_name, ParameterType::STRING);
		}
		// Get the value of position filter here
		$position = $this->getState('filter.position');
		if (!empty($position))
		{
			$query
				->where($db->quoteName('room.position') . ' LIKE :position')
				->bind(':position', $position, ParameterType::STRING);
		}
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
