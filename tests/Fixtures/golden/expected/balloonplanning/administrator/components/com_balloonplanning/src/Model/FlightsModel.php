<?php
/**
 * @package    BalloonPlanning
 * @subpackage BalloonPlanning
 * @version    6.0.0 alpha
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL3
 */

namespace Yepr\Component\BalloonPlanning\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Flights records.
 */
class FlightsModel extends ListModel
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
							'id','plannedflight.id'
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
			$db->quoteName('plannedflight.date'),
			$db->quoteName('plannedflight.morning_evening'),
			$db->quoteName('plannedflight.flight_number'),
			$db->quoteName('plannedflight.id')
		]);
		$query->from($db->quoteName('#__balloonplanning_plannedflight', 'plannedflight'));

		// Add the presentation fields from the joined tables
		$query->select($db->quoteName('balloon.call-sign', 'balloon'));
		$query->join(
			'LEFT',
			$db->quoteName('#__balloonplanning_balloon', 'balloon'),
				$db->quoteName('balloon.id') . ' = ' . $db->quoteName('plannedflight.balloon_id')
		);
		$query->select($db->quoteName('departureplace.place_name', 'departureplace'));
		$query->join(
			'LEFT',
			$db->quoteName('#__balloonplanning_departureplace', 'departureplace'),
				$db->quoteName('departureplace.id') . ' = ' . $db->quoteName('plannedflight.departureplace_id')
		);
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
