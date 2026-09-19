<?php
/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.9.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Field\LIonCore_M3;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Yepr\Component\Extengen\Administrator\Repository\ProjectFormRepository;


// The class name must always be the same as the filename (in camel case)
// extend the list field type
class LanguageReferenceField extends ListField
{
	//The field class must know its own type through the variable $type.
	protected $type = 'LanguageReference';

	/**
	 * Get the options for the list field: all Qualifiers (= Concepts + Annotations)  currently in the project
	 *
	 */
	public function getOptions()
	{
		$languageNameMap = [];
		$languageNameMap[0] = '&nbsp;';

		$AST = $this->initiateAST();
		if (!is_null($AST))
		{
			// TODO: GET ALL LANGUAGES IN THIS SYSTEM FROM DB
			// Get the entities that are currently in the model.
			// todo: cache this (in the AST) and in the project-model
			foreach ($AST->languageEntities as $languageEntity)
			{
				//if ($languageEntity->) // TODO: how to check if it is a language (= concept or annotation)?
				$languageNameMap[$language->language_id] = ucfirst($language->language_name);
			}

		}

        // use a for-each to iterate over the $languageNameMap
		$languageOptions = [];
        foreach($languageNameMap as $key => $languageName)
        {
	        // Set an array with the  value / text items.
	        $languageOptions[] = array("value" => $key, "text" => $languageName);
        }

        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $languageOptions);
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

		return (new ProjectFormRepository($this->getDatabase()))->findRaw($id);
	}

}
