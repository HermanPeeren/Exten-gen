<?php
/**
 * @package    MyConference
 * @subpackage Conference
 * @version    1.0.0
 *
 * @copyright  Herman Peeren - Yepr - 2023
 * @license    GPL 3.0
 */

namespace Yepr\Component\Conference\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Helper\TagsHelper;

/**
 * Item Model for a Room.
 */
class RoomModel extends AdminModel
{
	 /**
	 * The type alias for this content type.
	 *
	 * @var    string
	 */
	public $typeAlias = 'com_conference.room';

	/**
	 * The context used for the associations table
	 *
	 * @var    string
	 */
	protected $associationsContext = 'com_conference.item';

	/**
	 * Allowed batch commands
	 *
	 * @var  array
	 */

	protected $batch_commands = [
		'assetgroup_id' => 'batchAccess',
		'language_id'   => 'batchLanguage',
		'tag'           => 'batchTag',
		'user_id'       => 'batchUser'
	];


	/**
	 * Method to get the Room form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  \JForm|boolean  A \JForm object on success, false on failure
	 */
	public function getForm($data = [], $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm($this->typeAlias, 'room', ['control' => 'jform', 'load_data' => $loadData]);

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 */
	protected function loadFormData()
	{
		$app = Factory::getApplication();

		// Check the session for previously entered form data.
		$data = $app->getUserState('com_conference.edit.room.data', []);

		if (empty($data))
		{
			$data = $this->getItem();

			// Prime some default values. Todo: categories
			/*if ($this->getState('room.id') == 0)
			{
				$data->set('catid', $app->getInput()->get('catid', $app->getUserState('com_conference.room.filter.category_id'), 'int')); // todo: for categories the plural entityname or pagename (?) is used; 'foos' in examples
			}*/
		}

		//$this->preprocessData($this->typeAlias, $data);


		return $data;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed  Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);

		// TODO: associations
		// Load associated room items
		//$assoc = Associations::isEnabled();
		$assoc = false;

		if ($assoc)
		{
			$item->associations = [];

			if ($item->id != null)
			{
				// todo: not room but the corresponding entity (which can have a different name)!
				$associations = Associations::getAssociations('com_conference', '#__conference_room', 'com_conference.item', $item->id, 'id', null);

				foreach ($associations as $tag => $association)
				{
					$item->associations[$tag] = $association->id;
				}
			}
		}

		// TODO: tags --- N.B: tags is a protected property...
		// Load item tags
		/*if (!empty($item->id))
		{
			$item->tags = new TagsHelper;
			$item->tags->getTagIds($item->id, 'com_conference.room');
		}*/

		return $item;
	}

	public function save($data)
	{

		return parent::save($data);
	}

	// Override getTable to be sure the right table name is used (especially when page name is different from entity name).
	public function getTable($name = 'Room', $prefix = '', $options = [])
	{
	return parent::getTable($name, $prefix, $options);
	}

	// <extengen id="site.detailsmodel.methods">
	// </extengen>

}
