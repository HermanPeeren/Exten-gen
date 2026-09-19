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
 * Presentation_type Table class.
 */
class Presentation_typeTable extends Table implements TaggableTableInterface
{
	use TaggableTableTrait; 
	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_eventschedule.presentation_type';

		parent::__construct('#__eventschedule_presentation_type', 'id', $db);
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

	/** Stores a Presentation_type.
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


		// Insert or update the object based on presence of a key value.
		if ($key) {
			// Already have a table key, update the row.
			$this->_db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
		} else {
			// Don't have a table key, insert the row.
			$this->_db->insertObject($this->_tbl, $this, $this->_tbl_key);
		}


		return true;
	}



	/**
	 * Method to delete a presentation_type (and mappings of that presentation_type to related entities) from the database.
	 *
	 * @param   integer  $presentation_typeId  An optional presentation_type id.
	 *
	 * @return  boolean  True on success, false on failure.
	 *
	 * @see     \Joomla\CMS\Table\User handling of user groups
	 */
	public function delete($presentation_typeId = null):bool
	{
		// Set the primary key to delete.
		$k = $this->_tbl_key;

		if ($presentation_typeId) {

			$this->$k = (int) $presentation_typeId;
		}

		$key = (int) $this->$k;


		// Delete the presentation_type.
		$query->clear()
			->delete($this->_db->quoteName($this->_tbl))
			->where($this->_db->quoteName($this->_tbl_key) . ' = :key')
			->bind(':key', $key, ParameterType::INTEGER);
		$this->_db->setQuery($query);
		$this->_db->execute();

		return true;
	}

}
