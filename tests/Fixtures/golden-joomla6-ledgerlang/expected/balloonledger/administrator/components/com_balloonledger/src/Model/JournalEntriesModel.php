<?php
/**
 * @package    BalloonLedger
 * @subpackage BalloonLedger
 * @version    0.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL3
 */

namespace Yepr\Component\BalloonLedger\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of JournalEntries records.
 */
class JournalEntriesModel extends ListModel
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
							'id','journalentry.id'
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
			$db->quoteName('journalentry.number'),
			$db->quoteName('journalentry.entry_date'),
			$db->quoteName('journalentry.description'),
			$db->quoteName('journalentry.status'),
			$db->quoteName('journalentry.origin'),
			$db->quoteName('journalentry.external_id'),
			$db->quoteName('journalentry.id')
		]);
		$query->from($db->quoteName('#__balloonledger_journalentry', 'journalentry'));

		// Add the presentation fields from the joined tables
		$query->select($db->quoteName('journal.code', 'journal'));
		$query->join(
			'LEFT',
			$db->quoteName('#__balloonledger_journal', 'journal'),
				$db->quoteName('journal.id') . ' = ' . $db->quoteName('journalentry.journal_id')
		);
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
