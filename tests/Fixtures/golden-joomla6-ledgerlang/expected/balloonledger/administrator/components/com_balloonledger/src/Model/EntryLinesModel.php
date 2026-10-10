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
 * Methods supporting a list of EntryLines records.
 */
class EntryLinesModel extends ListModel
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
							'id','entryline.id'
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
			$db->quoteName('entryline.description'),
			$db->quoteName('entryline.debit'),
			$db->quoteName('entryline.credit'),
			$db->quoteName('entryline.id')
		]);
		$query->from($db->quoteName('#__balloonledger_entryline', 'entryline'));

		// Add the presentation fields from the joined tables
		$query->select($db->quoteName('journalentry.number', 'entry'));
		$query->join(
			'LEFT',
			$db->quoteName('#__balloonledger_journalentry', 'journalentry'),
				$db->quoteName('journalentry.id') . ' = ' . $db->quoteName('entryline.journalentry_id')
		);
		$query->select($db->quoteName('account.code', 'account'));
		$query->join(
			'LEFT',
			$db->quoteName('#__balloonledger_account', 'account'),
				$db->quoteName('account.id') . ' = ' . $db->quoteName('entryline.account_id')
		);
		$query->select($db->quoteName('taxcode.code', 'tax_code'));
		$query->join(
			'LEFT',
			$db->quoteName('#__balloonledger_taxcode', 'taxcode'),
				$db->quoteName('taxcode.id') . ' = ' . $db->quoteName('entryline.taxcode_id')
		);
		$query->select($db->quoteName('party.name', 'party'));
		$query->join(
			'LEFT',
			$db->quoteName('#__balloonledger_party', 'party'),
				$db->quoteName('party.id') . ' = ' . $db->quoteName('entryline.party_id')
		);
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
