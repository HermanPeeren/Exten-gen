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
 * Methods supporting a list of Talks records.
 */
class TalksModel extends ListModel
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
							'title','talk.title',
							'speaker_id','talk.speaker_id',
							'id','talk.id'
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
			$db->quoteName('talk.title'),
			$db->quoteName('talk.description'),
			$db->quoteName('talk.id')
		]);
		$query->from($db->quoteName('#__conference_talk', 'talk'));

		// Add the presentation fields from the joined tables
		$query->select($db->quoteName('speaker.name', 'speaker'));
		$query->join(
			'LEFT',
			$db->quoteName('#__conference_speaker', 'speaker'),
				$db->quoteName('speaker.id') . ' = ' . $db->quoteName('talk.speaker_id')
		);
		// Add filters to query
		// Get the value of title filter here
		$title = $this->getState('filter.title');
		if (!empty($title))
		{
			$query
				->where($db->quoteName('talk.title') . ' LIKE :title')
				->bind(':title', $title, ParameterType::STRING);
		}
		// Get the value of speaker filter here
		$speaker = $this->getState('filter.speaker');
		if (!empty($speaker))
		{
			$query
				->where($db->quoteName('talk.speaker_id') . ' LIKE :speaker')
				->bind(':speaker', $speaker, ParameterType::STRING);
		}
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
