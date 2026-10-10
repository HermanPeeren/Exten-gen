<?php
/**
 * @package    BalloonLedger
 * @subpackage BalloonLedger
 * @version    0.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL3
 */

namespace Yepr\Component\BalloonLedger\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Accounts records.
 */
class AccountsModel extends ListModel
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
							'id','account.id'
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
			$db->quoteName('account.code'),
			$db->quoteName('account.name'),
			$db->quoteName('account.nature'),
			$db->quoteName('account.normal_balance'),
			$db->quoteName('account.postable'),
			$db->quoteName('account.reconcilable'),
			$db->quoteName('account.active'),
			$db->quoteName('account.id')
		]);
		$query->from($db->quoteName('#__balloonledger_account', 'account'));



		// <extengen id="site.listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="site.listmodel.methods">
	// </extengen>

}
