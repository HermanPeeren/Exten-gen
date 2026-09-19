<?php
/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Form\Form;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\WorkflowBehaviorTrait;
use Joomla\CMS\MVC\Model\WorkflowModelInterface;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Table\TableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\UCM\UCMType;
use Joomla\CMS\Versioning\VersionableModelTrait;
use Joomla\CMS\Workflow\Workflow;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

use	Yepr\Component\Extengen\Administrator\Generator\LanguageStringUtil;


/**
 * Forms Diagram Model: to get the project form data from the db
 */
class FormsDiagramModel extends AdminModel
{
	/**
	 * The (internal) id of the project forms definition from which we generate form files
	 *
	 * @var   int|Integer
	 */
	protected int $projectFormId;
	/**
	 * Set the project id.
	 *
	 * @param   int  $projectId
	 */
	public function setProjectFormId(int $projectFormId): void
	{
		$this->projectFormId = $projectFormId;
	}

	/**
	 * Get the (json-encoded) form-data of the project that form the AST.
	 * todo: use this as private method and query the PlantUML-stuff from this model
	 *
	 * @return object the AST
	 */
	public function getAST(): object
	{
		// The id is set on this model by the controller, not taken from the
		// request: the model is told which record it is working on.
		$id = (int) $this->projectFormId;

		$model = (new ProjectFormRepository($this->getDatabase()))->findRaw($id);

		if ($model === null)
		{
			// The declared return type is not nullable, so without this a
			// missing or unreadable record arrives as a TypeError with
			// nothing in it to act on.
			throw new \RuntimeException(sprintf('Cannot read project form %d.', $id));
		}

		return $model;
	}

	/**
	 * NOT IN USE NOW (instead: directly query via getAST()).
	 * Method to get the project-data.
	 * Overriden to prevent initiating a non-existing Generator-Table.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed   Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
			$pk = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');

			// get the Project table
			$table = $this->getTable("Project");

			if ($pk > 0) {
				// Attempt to load the row.
				$return = $table->load($pk);

				// Check for a table object error.
				if ($return === false) {
					// If there was no underlying error, then the false means there simply was not a row in the db for this $pk.
					if (!$table->getError()) {
						$this->setError(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
					} else {
						$this->setError($table->getError());
					}

					return false;
				}
			}

			// Convert to the CMSObject before adding other data.
			$properties = $table->getProperties(1);
			$item = ArrayHelper::toObject($properties, CMSObject::class);

			if (property_exists($item, 'params')) {
				$registry = new Registry($item->params);
				$item->params = $registry->toArray();
			}

			return $item;
	}

	/**
	 * NOT USED ATM. BUT MUST BE IMPLEMENTED. MIGHT USE IN FUTURE.
	 * Method to get the row form.
	 *
	 * @param   array    $data      Data for the form
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not
	 *
	 * @return  Form|boolean  A Form object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		return false;
	}

}
