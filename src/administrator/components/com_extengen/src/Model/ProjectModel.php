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
// todo: clean up unused use clauses
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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Table\TableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\UCM\UCMType;
use Joomla\CMS\Versioning\VersionableModelTrait;
use Joomla\CMS\Workflow\Workflow;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;
use Yepr\Component\Extengen\Administrator\Reference\Er1;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;
use Yepr\Gen\Core\Reference\ReferenceIndex;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;

/**
 * Item Model for a project.
 */
class ProjectModel extends AdminModel
{
	/**
	 * The type alias for this content type.
	 *
	 * @var    string
	 */
	public $typeAlias = 'com_extengen.project';

	/**
	 * The context used for the associations table
	 *
	 * @var    string
	 */
	protected $associationsContext = 'com_extengen.item';

	/**
	 * Batch copy/move command. If set to false, the batch copy/move command is not supported
	 *
	 * @var  string
	 */
	protected $batch_copymove = 'category_id';

	/**
	 * Allowed batch commands
	 *
	 * @var array
	 */
	protected $batch_commands = array(
		'assetgroup_id' => 'batchAccess',
		'language_id'   => 'batchLanguage',
	);

	/**
	 * Method to get the row form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  Form|boolean  A Form object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// The half of a project that is a Joomla item: alias, published,
		// access, catid, ordering, params. 3.2 recorded why it is not
		// generated - none of it is derivable from a language.
		$form = $this->loadForm(
			'com_extengen.project',
			'project_chrome',
			array('control' => 'jform', 'load_data' => $loadData)
		);

		if (empty($form)) {
			return false;
		}

		// A project that does not exist yet gets the chrome and nothing else.
		// The language is chosen on this screen, and until it is saved there
		// is no answer to "which forms" - so showing one language's model half
		// while somebody picks another is showing them the wrong form. It also
		// cannot be filled in: the model half carries required fields, and
		// Joomla's validator refuses the save over fields belonging to a
		// language the project is not going to be written in.
		//
		// So a project is created, and then modelled. That is what binding at
		// creation means.
		if ((int) ($this->getItem()->id ?? 0) === 0) {
			return $form;
		}

		// And the half that is a model, from whichever metalanguage this
		// project is written in. ER1 arrives by exactly this route, as
		// project_er1.xml, so the built-in and an imported language are the
		// same case - which is what lets 3.5 turn ER1 into a package without
		// touching anything here.
		$entry = $this->metalanguage();

		if ($entry === null) {
			// Nothing to open it with. Before 3.5 this could not happen - an
			// unbound project fell back to the forms this component shipped -
			// and now that ER1 is a package like any other it can: somebody
			// removed the language this project is written in.
			//
			// The chrome alone, and a message. A project whose model cannot be
			// rendered is still a row somebody may need to look at, and opening
			// it with another language's forms would let a save reshape the
			// model to fit them.
			Factory::getApplication()->enqueueMessage(
				Text::sprintf(
					'COM_EXTENGEN_PROJECT_METALANGUAGE_UNKNOWN',
					(string) ($this->getItem()->metalanguage_key ?: '?'),
					(string) ($this->getItem()->metalanguage_version ?: '?')
				),
				'warning'
			);

			return $form;
		}

		$source = JPATH_ROOT . '/' . $entry->rootFormPath();

		if (!is_file($source)) {
			// A language whose files are gone - uninstalled, or half copied
			// between sites. Saying so beats an edit screen with no model on
			// it, which is what merging nothing would produce.
			// Enqueued rather than setError(), which is deprecated and which
			// nothing reads on this path anyway - the form would come back
			// with no model on it and no word of why. loadFormData() says the
			// same thing about the same screen.
			Factory::getApplication()->enqueueMessage(
				Text::sprintf('COM_EXTENGEN_PROJECT_METALANGUAGE_MISSING', $entry->label(), $entry->rootFormPath()),
				'warning'
			);

			return $form;
		}

		// No xpath. Form::load() with one runs it against the file and merges
		// whatever it returns, so '/form' merges the <form> element itself and
		// the result is a form nested inside a form - which renders as a
		// screen with no fields on it and reports nothing. Without one, a
		// document whose root is <form> contributes its children, which is the
		// merge this wants.
		$form->load((string) file_get_contents($source), true);

		// The strings on an imported language's forms come with it, at a path
		// the package names. Nothing else defines them, so without this every
		// label renders as its own constant in capitals.
		if (!$entry->isBuiltIn() && $entry->languageFile !== '') {
			Factory::getApplication()->getLanguage()->load(
				basename($entry->languageFile, '.ini'),
				JPATH_ROOT . '/' . rtrim($entry->formRoot, '/')
			);
		}

		return $form;
	}

	/**
	 * The metalanguage this project is written in.
	 *
	 * Never null, and an unbound project is written in ER1 - which is what
	 * every project in every existing database is, because there was nothing
	 * else when they were made.
	 *
	 * @return  MetalanguageEntry
	 */
	public function metalanguage(): ?MetalanguageEntry
	{
		$item = $this->getItem();

		return Metalanguages::forProject(
			$this->getDatabase(),
			(string) ($item->metalanguage_key ?? ''),
			(string) ($item->metalanguage_version ?? '')
		);
	}

	/**
	 * Everything in this project that a reference field can point at.
	 *
	 * The edit view puts this in the page once. Before 1.9 each reference
	 * dropdown loaded the project itself and rendered its own <option>
	 * tags, which meant one query per dropdown and, worse, choices that
	 * could only describe what was already in the database - so an entity
	 * added a minute ago could not be referred to until the project was
	 * saved.
	 *
	 * It is the model's job rather than the view's because it reads the
	 * stored model, and reading the stored model happens here or in the
	 * repository and nowhere else.
	 *
	 * @return  array  index and types, as <yepr-reference> expects them.
	 */
	public function getReferenceIndex(): array
	{
		$item = $this->getItem();

		$stored = null;

		if (!empty($item->form_data)) {
			try {
				$stored = Project::fromJson((string) $item->form_data)->raw();
			} catch (\JsonException | \InvalidArgumentException $e) {
				// A model that will not decode is reported by loadFormData(),
				// which runs for the same request. Saying it twice would put
				// the same warning on screen twice.
				$stored = null;
			}
		}

		// The mechanism is the shared library's, because Meta-gen and Gen-gen
		// ask the same question of their own models; the table says what this
		// particular language offers, which is the only thing that differs.
		//
		// An imported language brings its own, generated beside its forms by
		// the same walk that generated them - which is the whole reason 3.2
		// generated a table at all. ER1's is still PHP this component ships,
		// and 3.5 is where that stops being true.
		return ReferenceIndex::fromTable($this->referenceTable())->payload($stored);
	}

	/**
	 * The reference table of the language this project is written in.
	 *
	 * @return  array
	 */
	private function referenceTable(): array
	{
		$entry = $this->metalanguage();

		if ($entry === null) {
			return [];
		}

		$path = $entry->referenceTablePath();

		if ($path === '') {
			// A language the component ships rather than one it imported.
			// Nothing does this since 3.5 turned ER1 into a package, and the
			// branch stays because the library still allows one.
			return Er1::TABLE;
		}

		$file = JPATH_ROOT . '/' . $path;

		if (!is_file($file)) {
			return [];
		}

		try {
			$table = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			// A dropdown with nothing in it reads as "there is nothing to
			// point at", which is a lie worth not telling twice - the import
			// refuses a package whose files do not match their hashes, so
			// getting here means somebody edited one afterwards.
			return [];
		}

		return \is_array($table) ? $table : [];
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 */
	protected function loadFormData()
	{
		$item = $this->getItem();

		// A project that has never been saved has nothing stored yet, and the
		// form renders from its own defaults.
		if (empty($item->form_data)) {
			return new \stdClass();
		}

		// The binding lives in two columns and renders through one field, so
		// it is put back together on the way to the form. Without this the
		// dropdown on an existing project shows the first entry rather than
		// the language the project is actually written in - which is worse
		// than showing nothing, because it looks like an answer.
		$binding = (string) ($item->metalanguage_key ?? '') === ''
			? ''
			: $item->metalanguage_key . '|' . ($item->metalanguage_version ?? '');

		// Through the model type rather than a bare json_decode, so that every
		// read of a stored project goes through one place. The form wants the
		// values as stored, which is what raw() is.
		try {
			$stored = Project::fromJson((string) $item->form_data)->raw();

			$stored->metalanguage = $binding;

			return $stored;
		} catch (\JsonException | \InvalidArgumentException $e) {
			// A model that cannot be read must not take down the page somebody
			// needs in order to fix it. Enqueued rather than setError(), which
			// nothing reads on this path: the form would come back empty with
			// no word of why.
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

			return new \stdClass();
		}
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

		// Load associated extengen items
		$assoc = Associations::isEnabled();

		if ($assoc) {
			$item->associations = array();

			if ($item->id != null) {
				$associations = Associations::getAssociations('com_extengen', '#__extengen_projects', 'com_extengen.item', $item->id, 'id', null);

				foreach ($associations as $tag => $association) {
					$item->associations[$tag] = $association->id;
				}
			}
		}

		/*if (is_null($item->id))
		{
			$item->id = 0;
			//$item->form_data = '{}';
		}*/

		return $item;
	}

	/**
	 * Allows preprocessing of the Form object.
     *
     * @param   Form    $form   The form object
     * @param   array   $data   The data to be merged into the form object
     * @param   string  $group  The plugin group to be executed
	 *
	 * @return  void
	 */
	protected function preprocessForm(Form $form, $data, $group = 'content')
	{
		// Association contact items
		if (Associations::isEnabled()) {
			$languages = LanguageHelper::getContentLanguages(false, true, null, 'ordering', 'asc');

			if (count($languages) > 1) {
				$addform = new \SimpleXMLElement('<form />');
				$fields = $addform->addChild('fields');
				$fields->addAttribute('name', 'associations');
				$fieldset = $fields->addChild('fieldset');
				$fieldset->addAttribute('name', 'item_associations');

				foreach ($languages as $language) {
					$field = $fieldset->addChild('field');
					$field->addAttribute('name', $language->lang_code);
					$field->addAttribute('type', 'modal_extengen');
					$field->addAttribute('language', $language->lang_code);
					$field->addAttribute('label', $language->title);
					$field->addAttribute('translate_label', 'false');
					$field->addAttribute('select', 'true');
					$field->addAttribute('new', 'true');
					$field->addAttribute('edit', 'true');
					$field->addAttribute('clear', 'true');
				}

				$form->load($addform, false);
			}
		}

		parent::preprocessForm($form, $data, $group);
	}

    /**
     * Overriden method to save the form data.
     * All data is serialised in JSON to save the entire form_data
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     */
    public function save($data)
    {
        // Stamp the format the model is being written in. Projects saved before
        // this carry no version and read as 1.0, which is what they are; from
        // here on a stored model says so itself, so a later change to the shape
        // can be made without guessing what an older one meant.
        $data['modelVersion'] = Project::CURRENT_VERSION;

        // Which metalanguage this project is written in, out of the one field
        // that carries it and into the two columns that store it. Two columns
        // rather than one string because they are queried separately: 3.5 will
        // ask "is anything still written in this language" before letting one
        // be removed, and a LIKE over a packed value is not that question.
        //
        // It is taken out of the form data before it is serialised, because
        // the binding is not part of the model - a project's JSON describes
        // the thing being generated, and which forms it was typed into is a
        // fact about the row.
        $binding = (string) ($data['metalanguage'] ?? '');

        unset($data['metalanguage']);

        $form_data = json_encode($data);
        $data['form_data'] = $form_data;

        // Only on a new project. The field renders read-only once a project
        // has an id, and a post is not a thing to trust about that: changing
        // the binding under an existing model means the forms that opened it
        // stop describing what is stored.
        if (empty($data['id'])) {
            [$key, $version] = array_pad(explode('|', $binding, 2), 2, '');

            $data['metalanguage_key']     = $key;
            $data['metalanguage_version'] = $version;
        } else {
            unset($data['metalanguage_key'], $data['metalanguage_version']);
        }

        return parent::save($data);
    }
}
