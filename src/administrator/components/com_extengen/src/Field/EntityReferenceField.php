<?php
/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Field;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepository;


// The class name must always be the same as the filename (in camel case)
// extend the list field type
class EntityReferenceField extends ListField
{
	//The field class must know its own type through the variable $type.
	protected $type = 'EntityReference';

	/**
	 * Get the options for the list field: all entities currently in the project
	 *
	 */
	public function getOptions()
	{
		$entityNameMap = [];
		$entityNameMap[0] = '&nbsp;';

		$AST = $this->initiateAST();
		if (!is_null($AST))
		{
			// Get the entities that are currently in the model.
			// todo: cache this (in the AST) and in the project-model
			foreach ($AST->datamodel as $entity)
			{
				$entityNameMap[$entity->entity_id] = ucfirst($entity->entity_name);
			}

		}

        // use a for-each to iterate over the $entityNameMap
		$entityOptions = [];
        foreach($entityNameMap as $uuid => $entityName)
        {
	        // Set an array with the  value / text items.
	        $entityOptions[] = array("value" => $uuid, "text" => $entityName);
        }

        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $entityOptions);
        return $options;
    }


	/**
	 * Get the (json-encoded) form-data of the project that form the AST.
	 *
	 * @return object|null
	 */
	private function initiateAST(): ?object
	{
		// The id of the record being edited comes from the request: a reference
		// field is rendered inside that record's own form.
		$id = (int) Factory::getApplication()->getInput()->getInt('id');

		return (new ProjectRepository($this->getDatabase()))->find($id)?->raw();
	}

}
