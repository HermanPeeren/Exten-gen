<?php
/**
 * @package    MyConference
 * @subpackage Conference
 * @version    1.0.0
 *
 * @copyright  Herman Peeren - Yepr - 2023
 * @license    GPL 3.0
 */

namespace Yepr\Component\Conference\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Program records.
 */
class ProgramModel extends ListModel
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
							'room_id','program.room_id',
							'talk_id','program.talk_id',
							'time','program.time',
							'title','program.title',
							'id','program.id'
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
	 *
	 * @since   __BUMP_VERSION__
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$db = $this->getDatabase();
		$query = $db->getQuery(true);

		// Select the required fields from the table.
		$query->select([
			$db->quoteName('program.title'),
			$db->quoteName('program.time'),
			$db->quoteName('program.id')
		]);
		$query->from($db->quoteName('#__conference_program', 'program'));

		// Add the presentation fields from the joined tables
		$query->select($db->quoteName('talk.title', 'talk'));
		$query->join(
			'LEFT',
			$db->quoteName('#__conference_talk', 'talk'),
				$db->quoteName('talk.id') . ' = ' . $db->quoteName('program.talk_id')
		);
		$query->select($db->quoteName('room.room_name', 'room'));
		$query->join(
			'LEFT',
			$db->quoteName('#__conference_room', 'room'),
				$db->quoteName('room.id') . ' = ' . $db->quoteName('program.room_id')
		);

		// Add filters to query
		// Get the value of room filter here
		$room = $this->getState('filter.room');
		if (!empty($room))
		{
			$query
				->where($db->quoteName('program.room_id') . ' LIKE :room')
				->bind(':room', $room, ParameterType::STRING);
		}
		// Get the value of talk filter here
		$talk = $this->getState('filter.talk');
		if (!empty($talk))
		{
			$query
				->where($db->quoteName('program.talk_id') . ' LIKE :talk')
				->bind(':talk', $talk, ParameterType::STRING);
		}
		// Get the value of time filter here
		$time = $this->getState('filter.time');
		if (!empty($time))
		{
			$query
				->where($db->quoteName('program.time') . ' LIKE :time')
				->bind(':time', $time, ParameterType::STRING);
		}
		// Get the value of title filter here
		$title = $this->getState('filter.title');
		if (!empty($title))
		{
			$query
				->where($db->quoteName('program.title') . ' LIKE :title')
				->bind(':title', $title, ParameterType::STRING);
		}

		return $query;
	}


}
