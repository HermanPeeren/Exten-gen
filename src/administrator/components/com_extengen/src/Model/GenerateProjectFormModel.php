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
use Yepr\Component\Extengen\Administrator\Repository\ProjectFormRepository;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Yepr\Component\Extengen\Administrator\Generator\ProjectForms;

use	Yepr\Component\Extengen\Administrator\Generator\LanguageStringUtil;


/**
 * Generate Form Model
 */
class GenerateProjectFormModel extends AdminModel
{
	/**
	 * A log of all files that were created with the various generators
	 *
	 * @var array
	 */
	public array $log = [];

	/**
	 * The (internal) id of the project for which we generate files
	 *
	 * @var   int|Integer
	 */
	protected int $projectFormId;

	/**
	 * Set the project form id.
	 *
	 * @param   int  $projectFormId
	 */
	public function setProjectFormId(int $projectFormId): void
	{
		$this->projectFormId = $projectFormId;
	}

	/**
	 * Generate the form-files using various generators.
	 * This is the central place from where all concrete generators are called.
	 *
	 */
	public function generate()
	{
		// Initialise variables
		$AST = $this->initiateAST();
		$languageStringUtil = new LanguageStringUtil($AST);

		// Project Form Name
		$projectFormName = $AST->name;

		// Find the root(s) in the AST. For now: assume exactly 1 root. Todo: multiple roots.
		// walk through the AST and find the root; take that node and the tree under it


		// file paths for generated (language)files
		$extengenAdminPath = JPATH_ROOT . '/administrator/components/com_extengen/';
		$generatedFormFilesPath = $extengenAdminPath . 'forms/ProjectForms/' . $projectFormName . '/';

		$generatorNamespace = 'Yepr\\Component\\Extengen\\Administrator\\Model\\Generator\\';
		$this->log[] = "<b>=== XML-FORMS FOR " . $projectFormName . " GENERATED ===</b>";
		$generator = new ProjectForms($projectFormName, $AST, $languageStringUtil);
		$this->log = array_merge($this->log, $generator->generate());

		// todo: ? do we generate files or are we - more dynamically-  adding them to the db? How about version control then?

		// Add strings to the language files. TODO: can we add those language stings more dynamically to the db?
		$this->log[] = "&nbsp;";

		//$this->log[] = "<b>=== LANGUAGE STRINGS OF THE FORMS ===</b>";
		// todo: handle language strings

		/*
		 * Languagestrings not in use for projectforms at the moment
	     * (because the generated language strings should have to be added to the existing ones of this component).
		$languageTree = $languageStringUtil->getLangTree();
		$baseGeneratedFilePath = 'administrator/components/com_'.strtolower($componentName).'/';
		foreach ($languageTree as $section_name => $section)
		{
			switch ($section_name)
			{
				case 'backend':
				case 'sys':
					$generatedFilePath = 'administrator/components/com_'.strtolower($componentName) .'/language/';
					break;
				case 'frontend':
					$generatedFilePath = 'components/com_'.strtolower($componentName) .'/language/';
					break;
			}
			foreach ($section->languages as $language)
			{
				$languageFolderName = $language->language_code . '-' . $language->country_code;

				// Create the directory for the generated files if it doesn't exist
				$generatedDirectory = $generatedFilesPathComponent . $generatedFilePath . $languageFolderName;
				if (!file_exists($generatedDirectory)) {
					mkdir($generatedDirectory, 0755, true);
				}

				// Create the content of the language file
				$languageContent = [];
				foreach ($language->key_value_pairs as $keyValuePair)
				{
					$languageContent[] = $keyValuePair->language_string . '="' . $keyValuePair->locale_string . '"';
				}

				// Sort language strings alphabetically
				sort($languageContent);

				// todo: Add a heading to language string files with project, copyright, license and version

				// File name
				$sys = "";
				if ($section_name == 'sys')
				{
					$sys = ".sys";
				}
				$generatedFileName ='com_' . strtolower($componentName) . $sys . '.ini';

				// Write the file
				$languageFile = fopen( $generatedDirectory . "/" . $generatedFileName, "w") or die("Unable to open file!");
				fwrite($languageFile, implode("\n",$languageContent));
				fclose($languageFile);
				$this->log[] = $generatedFilePath . $languageFolderName . '/' . $generatedFileName . ' generated';
			}
		}*/
	}

	/**
	 * Get the (json-encoded) form-data of the project that form the AST.
	 *
	 * @return object
	 */
	private function initiateAST(): object
    {

		// The id is set on this model by the controller, not taken from the
		// request: the model is told which record it is working on.
		$id = (int) $this->projectFormId;
$model = (new ProjectFormRepository($this->getDatabase()))->findRaw($id);
if ($model === null) {
// The declared return type is not nullable, so without this a
			// missing or unreadable record arrives as a TypeError with
			// nothing in it to act on.
			throw new \RuntimeException(sprintf('Cannot read project form %d.', $id));
}

		return $model;
    }

	/**
	 * The table this model reads, which is the ProjectForm table.
	 *
	 * This replaced a copy of AdminModel::getItem() whose only reason to
	 * exist was the same one: without it, AdminModel asks for a table
	 * named after the model - ERDTable, GenerateTable - and there is no
	 * such thing. Saying so here is one line instead of thirty, and it
	 * leaves getItem() to the parent, which is where the behaviour was
	 * copied from in the first place.
	 *
	 * @param   string  $name     The table name.
	 * @param   string  $prefix   The class prefix.
	 * @param   array   $options  Configuration for the table.
	 *
	 * @return  \Joomla\CMS\Table\Table
	 */
	public function getTable($name = 'ProjectForm', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}


	/**
	 * NOT USED ATM. BUT MUST BE IMPLEMENTED. MIGHT USE IN FUTURE TO CHOOSE GENERATORS.
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
