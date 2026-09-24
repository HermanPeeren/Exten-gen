<?php
/**
 * @package    Extengen
 * @subpackage Extengen
 * @version    1.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL 3.0
 */

namespace Yepr\Component\Extengen\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * Methods supporting a list of Metalanguages records.
 */
class MetalanguagesModel extends ListModel
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
							'lang_key','metalanguage.lang_key',
							'id','metalanguage.id'
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
			$db->quoteName('metalanguage.lang_key'),
			$db->quoteName('metalanguage.version'),
			$db->quoteName('metalanguage.name'),
			$db->quoteName('metalanguage.root'),
			$db->quoteName('metalanguage.form_root'),
			$db->quoteName('metalanguage.language_file'),
			$db->quoteName('metalanguage.manifest'),
			$db->quoteName('metalanguage.imported'),
			$db->quoteName('metalanguage.id')
		]);
		$query->from($db->quoteName('#__extengen_metalanguage', 'metalanguage'));

		// Add filters to query
		// Get the value of lang_key filter here
		$lang_key = $this->getState('filter.lang_key');
		if (!empty($lang_key))
		{
			$query
				->where($db->quoteName('metalanguage.lang_key') . ' LIKE :lang_key')
				->bind(':lang_key', $lang_key, ParameterType::STRING);
		}
		// <extengen id="listmodel.query">
		// </extengen>

		return $query;
	}

	// <extengen id="listmodel.methods">
	// </extengen>

}
