<?php
/**
 * @package     BalloonLedger
 * @subpackage  com_balloonledger
 * @version     0.1.0
 *
 *
 * @copyright   Yepr, Herman Peeren
 * @license     GPL3
 */

namespace Yepr\Component\BalloonLedger\Administrator\Table;

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
 * Journal Table class.
 */
class JournalTable extends Table implements TaggableTableInterface
{
	use TaggableTableTrait; 
	/**
	 * Constructor
	 *
	 * @param   DatabaseDriver  $db  Database connector object
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->typeAlias = 'com_balloonledger.journal';

		parent::__construct('#__balloonledger_journal', 'id', $db);
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

		// A date or a time left empty on the form arrives as '', which MySQL
		// refuses for a date column in strict mode. NULL is what "no date" is,
		// and store() writes nulls, so clearing a date also clears it in the row.
		foreach ($this->getFields() as $name => $field) {
			if (isset($this->$name) && $this->$name === '' && preg_match('/^(date|datetime|time|timestamp)\b/i', (string) $field->Type)) {
				$this->$name = null;
			}
		}

		// What the model marks unique, which the install sql keys unique too:
		// refused here first, so the person gets a sentence rather than a
		// database error. An empty value is no value - stored as NULL where the
		// column allows it, and NULLs never collide. A natural key (isprimary)
		// may not be empty at all.
		//
		// Column => [the message when it is empty, or '' when it may be; the
		// message when another row has it]. By name rather than as properties,
		// because a column name may hold a character a PHP name cannot.
		$unique  = [
			'code' => [
				'COM_BALLOONLEDGER_JOURNAL_CODE_REQUIRED',
				'COM_BALLOONLEDGER_JOURNAL_CODE_NOT_UNIQUE',
			],
		];
		$columns = $this->getFields();

		foreach ($unique as $column => [$required, $taken]) {
			if (($this->$column ?? null) === '' && ($columns[$column]->Null ?? '') === 'YES') {
				$this->$column = null;
			}

			$value = $this->$column ?? null;

			if ($value === null || $value === '') {
				if ($required !== '') {
					$this->setError(Text::_($required));

					return false;
				}

				continue;
			}

			if (!$this->isUnique($column)) {
				$this->setError(Text::sprintf($taken, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8')));

				return false;
			}
		}

		// <extengen id="table.check">
		// </extengen>

		return true;
	}

	/**
	 * Whether no other row has this one's value in a column.
	 *
	 * @param   string  $column  The column, one the model marks unique.
	 *
	 * @return  boolean
	 */
	private function isUnique(string $column): bool
	{
		$db    = $this->getDatabase();
		$id    = (int) $this->id;
		$value = (string) $this->$column;
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName($this->_tbl))
			->where($db->quoteName($column) . ' = :value')
			->where($db->quoteName('id') . ' != :id')
			->bind(':value', $value)
			->bind(':id', $id, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult() === 0;
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

	/** Stores a Journal.
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


		// Insert or update the object based on presence of a key value.
		if ($key) {
			// Already have a table key, update the row.
			$db->updateObject($this->_tbl, $this, $this->_tbl_key, $updateNulls);
		} else {
			// Don't have a table key, insert the row.
			$db->insertObject($this->_tbl, $this, $this->_tbl_key);
		}


		return true;
	}



	/**
	 * Method to delete a journal (and mappings of that journal to related entities) from the database.
	 *
	 * @param   integer  $journalId  An optional journal id.
	 *
	 * @return  boolean  True on success, false on failure.
	 *
	 * @see     \Joomla\CMS\Table\User handling of user groups
	 */
	public function delete($journalId = null):bool
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true);

		// Set the primary key to delete.
		$k = $this->_tbl_key;

		if ($journalId) {

			$this->$k = (int) $journalId;
		}

		$key = (int) $this->$k;


		// Delete the journal.
		$query->clear()
			->delete($db->quoteName($this->_tbl))
			->where($db->quoteName($this->_tbl_key) . ' = :key')
			->bind(':key', $key, ParameterType::INTEGER);
		$db->setQuery($query);
		$db->execute();

		return true;
	}

	// <extengen id="table.methods">
	// </extengen>

}
