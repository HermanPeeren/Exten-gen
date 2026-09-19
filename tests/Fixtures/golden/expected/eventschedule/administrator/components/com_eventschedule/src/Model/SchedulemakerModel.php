<?php
/**
 * @package    EventSchedule
 * @subpackage eventschedule
 * @version    1.0.1
 *
 * @copyright  Herman Peeren, Yepr
 * @license    GPL vs3+
 */

namespace Yepr\Component\eventschedule\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Schedulemaker records.
 */
class SchedulemakerModel extends ListModel
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
							'id','locator.id'
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
			$db->quoteName('locator.starttime'),
			$db->quoteName('locator.endtime'),
			$db->quoteName('locator.id')
		]);
		$query->from($db->quoteName('#__eventschedule_locator', 'locator'));

		// Add the presentation fields from the joined tables
		$query->select($db->quoteName('track.name', 'track'));
		$query->join(
			'LEFT',
			$db->quoteName('#__eventschedule_track', 'track'),
				$db->quoteName('track.id') . ' = ' . $db->quoteName('locator.track_id')
		);
		$query->select($db->quoteName('dayschedule.name', 'dayschedule'));
		$query->join(
			'LEFT',
			$db->quoteName('#__eventschedule_dayschedule', 'dayschedule'),
				$db->quoteName('dayschedule.id') . ' = ' . $db->quoteName('locator.dayschedule_id')
		);
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
