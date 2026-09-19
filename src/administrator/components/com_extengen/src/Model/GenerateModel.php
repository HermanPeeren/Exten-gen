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
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Target\Joomla4Target;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepository;
use Yepr\Gen\Core\Model\ValidationException;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Target\Target;


/**
 * Generate Model
 */
class GenerateModel extends AdminModel
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
	protected int $projectId;

	/**
	 * The type of output we generate files for, for instance "Joomla4".
	 * There must be a subdirectory with this name with the concrete generators.
	 * When templates are used, they must be in a subdirectory under /generator_templates with that same name. todo: templates in db
	 *
	 * @var   string
	 */
	protected array $outputTypes = ['Joomla4']; //todo: set other output types

	/**
	 * Set the project id.
	 *
	 * @param   int  $projectId
	 */
	public function setProjectId(int $projectId): void
	{
		$this->projectId = $projectId;
	}

	/**
	 * Generate the files for this project.
	 *
	 * Everything now goes through the shared pipeline: it validates the project,
	 * runs the target's generators in order, and hands back the whole file set in
	 * memory. Writing it to disk happens here, afterwards and all at once, so a
	 * run that fails part way through leaves no half a component behind.
	 *
	 * @return  void
	 */
	public function generate()
	{
		$project = $this->loadProject();
		$target  = new Joomla4Target(
			JPATH_ROOT . '/administrator/components/com_extengen/generator_templates',
			JPATH_ROOT . '/administrator/components/com_extengen/compilation_cache'
		);

		$generators = $target->generators();

		try
		{
			$files = (new Pipeline())->run($project, new Target(
				$target->id(),
				$target->label(),
				$target->validator(),
				...$generators
			));
		}
		catch (ValidationException $e)
		{
			foreach ($e->getErrors() as $problem)
			{
				$this->log[] = '<b>' . htmlspecialchars($problem, ENT_QUOTES, 'UTF-8') . '</b>';
			}

			throw $e;
		}

		foreach ($generators as $generator)
		{
			$this->log = array_merge($this->log, $generator->log());
		}

		$this->write($files, $project);
	}

	/**
	 * Put the generated file set on disk, under the component's output directory.
	 *
	 * @param   FileCollection  $files          What was generated.
	 * @param   string          $componentName  Names the output directory.
	 *
	 * @return  void
	 */
	private function write(FileCollection $files, Project $project): void
	{
		$componentName = $project->componentName();
		$generated     = JPATH_ROOT . '/administrator/components/com_extengen/generated/' . $componentName;
		$root          = $generated . '/Joomla4/com_' . strtolower($componentName);

		if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root))
		{
			throw new \RuntimeException('Cannot create ' . $root);
		}

		$writer = new ZipWriter();

		// The archive is the deliverable: it is what somebody installs, and it
		// is the only form in which the output is a single thing that can be
		// handed to Joomla. The version is in its name because a downloads
		// folder full of identically named packages says nothing about which
		// is which, and the one that matters is rarely the newest by date.
		$version = trim((string) ($project->manifest()->version ?? '')) ?: '0.0.0';
		$archive = $generated . '/com_' . strtolower($componentName) . '-' . $version . '.zip';

		$writer->write($files, $archive);

		// And the tree beside it, because that is how generated output has
		// always been read here - opened, compared, looked through. It costs
		// nothing to keep and it is the only way to see a diff between runs
		// without unpacking anything.
		//
		// The writer re-checks every path against this root before writing, on
		// top of the collection having rejected anything that escapes.
		$writer->writeToDirectory($files, $root);

		$this->log[] = '&nbsp;';
		$this->log[] = '<b>' . count($files) . ' files</b>';
		$this->log[] = 'package: ' . $archive;
		$this->log[] = 'unpacked: ' . $root;
	}

	/**
	 * The project being generated from.
	 *
	 * @return  Project
	 */
	private function loadProject(): Project
	{
		$project = (new ProjectRepository($this->getDatabase()))->find((int) $this->projectId);

		if ($project === null)
		{
			throw new \RuntimeException(sprintf('Cannot read project %d.', $this->projectId));
		}

		return $project;
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
		$id = (int) $this->projectId;

		$model = (new ProjectRepository($this->getDatabase()))->find($id)?->raw();

		if ($model === null)
		{
			// The declared return type is not nullable, so without this a
			// missing or unreadable record arrives as a TypeError with
			// nothing in it to act on.
			throw new \RuntimeException(sprintf('Cannot read project %d.', $id));
		}

		return $model;
	}

	/**
	 * NOT IN USE NOW (instead: directly query via initiateAST()).
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
