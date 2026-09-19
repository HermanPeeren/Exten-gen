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
 * Speaker Table class.
 */
class SpeakerTable extends Table implements TaggableTableInterface
{
	use TaggableTableTrait; 
	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_eventschedule.speaker';

		parent::__construct('#__eventschedule_speaker', 'id', $db);
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


	// Set the presentationIds from the comma separated string of presentation-ids
	if ($return && array_key_exists('presentation_ids', $array)) {
		$this->presentation_ids = $array['presentation_ids'];
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
		if ($this->publish_down > $this->_db->getNullDate() && $this->publish_down < $this->publish_up) {
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

	/** Stores a Speaker.
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

		// Get the table key and key value.
		$k   = $this->_tbl_key;
		$key = $this->$k;


		// Store presentationIds locally so as to not update directly.
		$presentationIds = $this->presentation_ids;
		unset($this->presentation_ids);

		// Insert or update the object based on presence of a key value.
		if ($key) {
			// Already have a table key, update the row.
			$this->_db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
		} else {
			// Don't have a table key, insert the row.
			$this->_db->insertObject($this->_tbl, $this, $this->_tbl_key);
		}


		// Reset presentationIds to the local object.
		$this->presentation_ids = $presentationIds;

		$query = $this->_db->getQuery(true);

		// Store the presentationId data if the speaker data was saved.
		if (\is_array($this->presentation_ids) && \count($this->presentation_ids)) {
			$speakerId = (int) $this->id;

			// Grab all presentationIds for the speaker, as is stored in the junction table
			$query->clear()
				->select($this->_db->quoteName('presentation_id'))
				->from($this->_db->quoteName('#__eventschedule_presentation_speaker'))
				->where($this->_db->quoteName('speaker_id') . ' = :speakerid')
				->order($this->_db->quoteName('presentation_id') . ' ASC')
				->bind(':speakerid', $speakerId, ParameterType::INTEGER);

			$this->_db->setQuery($query);
			$presentationIdsInDb = $this->_db->loadColumn();

			// Loop through them and check if database contains something $this->presentationIds does not
			if (\count($presentationIdsInDb)) {
				$deletePresentationIds = [];

				foreach ($presentationIdsInDb as $storedPresentationId) {
					if (\in_array($storedPresentationId, $this->presentation_ids)) {
						// It already exists, no action required, so remove it from $presentationIds
						$presentationIds = array_diff($presentationIds,[$storedPresentationId]);
					} else {
						$deletePresentationIds[] = (int) $storedPresentationId;
					}
				}

				if (\count($deletePresentationIds)) {
					$query->clear()
						->delete($this->_db->quoteName('#__eventschedule_presentation_speaker'))
						->where($this->_db->quoteName('speaker_id') . ' = :speakerId')
						->whereIn($this->_db->quoteName('presentation_id'), $deletePresentationIds)
						->bind(':speakerId', $speakerId, ParameterType::INTEGER);

					$this->_db->setQuery($query);
					$this->_db->execute();
				}

				unset($deletePresentationIds);
			}

			// If there is anything left in $presentationIds it needs to be inserted
			if (\count($presentationIds)) {
				// Set the new actor presentationIds in the db junction table.
				$query->clear()
					->insert($this->_db->quoteName('#__eventschedule_presentation_speaker'))
					->columns([$this->_db->quoteName('speaker_id'), $this->_db->quoteName('presentation_id')]);

				foreach ($presentationIds as $presentationId) {
					$query->values(
						implode(
							',',
							$query->bindArray(
								[$this->id , $presentationId],
								[ParameterType::INTEGER, ParameterType::INTEGER]
							)
						)
					);
				}

				$this->_db->setQuery($query);
				$this->_db->execute();
			}

			unset($presentationIds);
		}

		return true;
	}



	/**
	 * Method to delete a speaker (and mappings of that speaker to related entities) from the database.
	 *
	 * @param   integer  $speakerId  An optional speaker id.
	 *
	 * @return  boolean  True on success, false on failure.
	 *
	 * @see     \Joomla\CMS\Table\User handling of user groups
	 */
	public function delete($speakerId = null):bool
	{
		// Set the primary key to delete.
		$k = $this->_tbl_key;

		if ($speakerId) {

			$this->$k = (int) $speakerId;
		}

		$key = (int) $this->$k;


		// Delete the speaker from the presentation_speaker junction table.
		$query = $this->_db->getQuery(true)
			->delete($this->_db->quoteName('#__eventschedule_presentation_speaker'))
			->where($this->_db->quoteName('speaker_id') . ' = :key')
			->bind(':key', $key, ParameterType::INTEGER);
		$this->_db->setQuery($query);
		$this->_db->execute();


		// Delete the speaker.
		$query->clear()
			->delete($this->_db->quoteName($this->_tbl))
			->where($this->_db->quoteName($this->_tbl_key) . ' = :key')
			->bind(':key', $key, ParameterType::INTEGER);
		$this->_db->setQuery($query);
		$this->_db->execute();

		return true;
	}

}
