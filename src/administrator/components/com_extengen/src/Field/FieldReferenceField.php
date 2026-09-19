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


// The class name must always be the same as the filename (in camel case)
// extend the list field type
class FieldReferenceField extends ListField
{
	//The field class must know its own type through the variable $type.
	protected $type = 'FieldReference';

	/**
	 * Get the options for the list field: all entities currently in the project
	 *
	 */
	public function getOptions()
	{
		$fieldNameMap = [];
		$fieldNameMap[0] = '&nbsp;';

		// Get the entities and fields that are currently in the model.
		// todo: cache this (in the AST) and in the project-model
		$AST = $this->initiateAST();
		
		// Get the id of the entity for which we want to show the fields
		$entityId    = $this->form->getValue('entity_id');
		
		// Only add fields when an entity is selected
		if (($entityId!=0) && !is_null($AST))
		{
			// Find the entity
			$currentEntity = null;
			foreach ($AST->datamodel as $entity)
			{
				if ($entity->entity_id == $entityId)
				{
					$currentEntity = $entity;
				}
			}

			// get all fields for that entity (including references to other entities)
			if (!is_null($currentEntity))
			{
				foreach ($entity->field as $field)
				{
					$fieldNameMap[$field->field_id] = ucfirst($field->field_name);
				}
			}
			
		}

		$fieldOptions = [];
		foreach($fieldNameMap as $uuid => $fieldName)
		{
			// Set an array with the  value / text items.
			$fieldOptions[] = array("value" => $uuid, "text" => $fieldName);
		}
		
        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $fieldOptions);
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
