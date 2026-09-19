<?php
/**
 * @package     EventSchedule
 * @subpackage  com_eventschedule
 * @version     1.0.1
 *
 *
 * @copyright   Herman Peeren, Yepr
 * @license     GPL vs3+
 */

namespace Yepr\Component\Eventschedule\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\Database\ParameterType;

/**
 * Track Table class.
 */
class TrackTable extends Table implements TaggableTableInterface
{
	use TaggableTableTrait; 
	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_eventschedule.track';

		parent::__construct('#__eventschedule_track', 'id', $db);
	}


	/**
	* Method to bind the actor and events data.
	*
	* @param   array  $array   The data to bind.
	* @param   mixed  $ignore  An array or space separated list of fields to ignore.
	*
	* @return  boolean  True on success, false on failure.
	*/
	public function bind($array, $ignore = ''):bool
	{
	// Attempt to bind the data.
	$return = parent::bind($array, $ignore);


	// Set the dayscheduleIds from the comma separated string of dayschedule-ids
	if ($return && array_key_exists('dayschedule_ids', $array)) {
		$this->dayschedule_ids = $array['dayschedule_ids'];
	}

	return $return;
	}


	/**
	 * Generate a valid alias from title / date.
	 * Remains public to be able to check for duplicated alias before saving
	 *
	 * @return  string
	 */
	public function generateAlias()
	{
		if (empty($this->alias)) {
			$this->alias = $this->name;
		}

		$this->alias = ApplicationHelper::stringURLSafe($this->alias, $this->language);

		if (trim(str_replace('-', '', $this->alias)) == '') {
			$this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
		}

		return $this->alias;
	}

	/**
	 * Overloaded check function
	 *
	 * @return  boolean
	 *
	 * @see     Table::check
	 */
	public function check()
	{
		try {
			parent::check();
		} catch (\Exception $e) {
			$this->setError($e->getMessage());

			return false;
		}
/*
		// Check the publish down date is not earlier than publish up.
		if ($this->publish_down > $this->getDatabase()->getNullDate() && $this->publish_down < $this->publish_up) {
			$this->setError(Text::_('JGLOBAL_START_PUBLISH_AFTER_FINISH'));

			return false;
		}

		// Set publish_up, publish_down to null if not set
		if (!$this->publish_up) {
			$this->publish_up = null;
		}

		if (!$this->publish_down) {
			$this->publish_down = null;
		}*/

		return true;
	}

	/**
	 * Get the type alias
	 *
	 * @return  string  The alias as described above
	 */
	public function getTypeAlias()
	{
		return $this->typeAlias;
	}

	/** Stores a Track.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean                True on success, false on failure.
	 */
	public function store($updateNulls = true)
	{
		// Transform the params field
		if (is_array($this->params)) {
			$registry = new Registry($this->params);
			$this->params = (string) $registry;
		}

		$db = $this->getDatabase();

		// Get the table key and key value.
		$k   = $this->_tbl_key;
		$key = $this->$k;


		// Store dayscheduleIds locally so as to not update directly.
		$dayscheduleIds = $this->dayschedule_ids;
		unset($this->dayschedule_ids);

		// Insert or update the object based on presence of a key value.
		if ($key) {
			// Already have a table key, update the row.
			$db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
		} else {
			// Don't have a table key, insert the row.
			$db->insertObject($this->_tbl, $this, $this->_tbl_key);
		}


		// Reset dayscheduleIds to the local object.
		$this->dayschedule_ids = $dayscheduleIds;

		$query = $db->getQuery(true);

		// Store the dayscheduleId data if the track data was saved.
		if (\is_array($this->dayschedule_ids) && \count($this->dayschedule_ids)) {
			$trackId = (int) $this->id;

			// Grab all dayscheduleIds for the track, as is stored in the junction table
			$query->clear()
				->select($db->quoteName('dayschedule_id'))
				->from($db->quoteName('#__eventschedule_dayschedule_track'))
				->where($db->quoteName('track_id') . ' = :trackid')
				->order($db->quoteName('dayschedule_id') . ' ASC')
				->bind(':trackid', $trackId, ParameterType::INTEGER);

			$db->setQuery($query);
			$dayscheduleIdsInDb = $db->loadColumn();

			// Loop through them and check if database contains something $this->dayscheduleIds does not
			if (\count($dayscheduleIdsInDb)) {
				$deleteDayScheduleIds = [];

				foreach ($dayscheduleIdsInDb as $storedDayScheduleId) {
					if (\in_array($storedDayScheduleId, $this->dayschedule_ids)) {
						// It already exists, no action required, so remove it from $dayscheduleIds
						$dayscheduleIds = array_diff($dayscheduleIds,[$storedDayScheduleId]);
					} else {
						$deleteDayScheduleIds[] = (int) $storedDayScheduleId;
					}
				}

				if (\count($deleteDayScheduleIds)) {
					$query->clear()
						->delete($db->quoteName('#__eventschedule_dayschedule_track'))
						->where($db->quoteName('track_id') . ' = :trackId')
						->whereIn($db->quoteName('dayschedule_id'), $deleteDayScheduleIds)
						->bind(':trackId', $trackId, ParameterType::INTEGER);

					$db->setQuery($query);
					$db->execute();
				}

				unset($deleteDayScheduleIds);
			}

			// If there is anything left in $dayscheduleIds it needs to be inserted
			if (\count($dayscheduleIds)) {
				// Set the new actor dayscheduleIds in the db junction table.
				$query->clear()
					->insert($db->quoteName('#__eventschedule_dayschedule_track'))
					->columns([$db->quoteName('track_id'), $db->quoteName('dayschedule_id')]);

				foreach ($dayscheduleIds as $dayscheduleId) {
					$query->values(
						implode(
							',',
							$query->bindArray(
								[$this->id , $dayscheduleId],
								[ParameterType::INTEGER, ParameterType::INTEGER]
							)
						)
					);
				}

				$db->setQuery($query);
				$db->execute();
			}

			unset($dayscheduleIds);
		}

		return true;
	}



	/**
	 * Method to delete a track (and mappings of that track to related entities) from the database.
	 *
	 * @param   integer  $trackId  An optional track id.
	 *
	 * @return  boolean  True on success, false on failure.
	 *
	 * @see     \Joomla\CMS\Table\User handling of user groups
	 */
	public function delete($trackId = null):bool
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true);

		// Set the primary key to delete.
		$k = $this->_tbl_key;

		if ($trackId) {

			$this->$k = (int) $trackId;
		}

		$key = (int) $this->$k;


		// Delete the track from the dayschedule_track junction table.
		$query->clear()
			->delete($db->quoteName('#__eventschedule_dayschedule_track'))
			->where($db->quoteName('track_id') . ' = :key')
			->bind(':key', $key, ParameterType::INTEGER);
		$db->setQuery($query);
		$db->execute();


		// Delete the track.
		$query->clear()
			->delete($db->quoteName($this->_tbl))
			->where($db->quoteName($this->_tbl_key) . ' = :key')
			->bind(':key', $key, ParameterType::INTEGER);
		$db->setQuery($query);
		$db->execute();

		return true;
	}

}
