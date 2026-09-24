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
use Yepr\Component\Extengen\Administrator\CustomCode\SlotCatalogue;
use Yepr\Component\Extengen\Administrator\Generator\Generator;
use Yepr\Component\Extengen\Administrator\Generator\LanguageContext;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Component\Extengen\Administrator\Generator\Target\Targets;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Core\Output\ProtectedRegionMerger;
use Yepr\Gen\Core\Reference\ReferenceIndex;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;
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
	 * Which target the project is generated into.
	 *
	 * @var   string
	 */
	protected string $targetId = Targets::DEFAULT;

	/**
	 * The type of output we generate files for, for instance "Joomla6".
	 * There must be a subdirectory with this name with the concrete generators.
	 * When templates are used, they must be in a subdirectory under /generator_templates with that same name. todo: templates in db
	 *
	 * @var   string[]
	 */
	protected array $outputTypes = ['Joomla6']; //todo: set other output types

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
	 * Set which target to generate into: step 4.4.
	 *
	 * Silently ignored when the id is not one this component has. A screen
	 * reached with `&target=` typed by hand should produce the default
	 * rather than an error page, and there is no user input to validate
	 * here - the registry is the whitelist.
	 *
	 * @param   string  $targetId  A registered target id.
	 *
	 * @return  void
	 */
	public function setTargetId(string $targetId): void
	{
		$this->targetId = $targetId;
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

		// Which language this project is written in, before anything reads the
		// model. A selector that follows a reference needs that language's
		// reference table to follow it with, and these rules are only about one
		// language - so this both refuses the run and supplies the table.
		LanguageContext::use(ReferenceIndex::fromTable(
			Metalanguages::referenceTable($this->language())
		));

		try {
			$this->runGenerators($project);
		} finally {
			// One run must not decide what the next one follows. The screens
			// are separate requests, but the acceptance checks are not.
			LanguageContext::reset();
		}
	}

	/**
	 * Run the target's generators over the project and write what they made.
	 *
	 * @param   Project  $project  The model to generate from.
	 *
	 * @return  void
	 */
	private function runGenerators(Project $project): void
	{
		// Out of the registry rather than constructed by name. 4.4 added a
		// second target and this is the line that had to change for it - one
		// line, in the model, and nothing in the pipeline, which is what 0.4
		// was for.
		$targets = Targets::registry(
			JPATH_ROOT . '/administrator/components/com_extengen/generator_templates',
			JPATH_ROOT . '/administrator/components/com_extengen/compilation_cache'
		);

		$target = $targets->has($this->targetId)
			? $targets->get($this->targetId)
			: $targets->get(Targets::DEFAULT);

		$generators = $target->generators();

		try {
			$files = (new Pipeline())->run($project, new Target(
				$target->id(),
				$target->label(),
				$target->validator(),
				...$generators
			));
		} catch (ValidationException $e) {
			foreach ($e->getErrors() as $problem) {
				$this->log[] = '<b>' . htmlspecialchars($problem, ENT_QUOTES, 'UTF-8') . '</b>';
			}

			throw $e;
		}

		foreach ($generators as $generator) {
			// Keeping a log is this project's habit rather than something
			// the shared GeneratorInterface promises, so it is asked for
			// where it exists instead of widening that interface for it.
			if ($generator instanceof Generator) {
				$this->log = array_merge($this->log, $generator->log());
			}
		}

		$this->write($files, $project, $target->id());
	}

	/**
	 * The metalanguage this project is written in, or refuse to generate.
	 *
	 * Three ways to fail and all three are refusals, because the alternative is
	 * not "generate a bit less" - it is a component with empty views and no
	 * indication of why. Every selector that finds the pages of a section goes
	 * through the reference table; without the right one the join returns
	 * nothing, every page-shaped rule fires zero times, and what comes out is a
	 * plausible-looking package missing half its files.
	 *
	 * The interesting one is the third, and 4.5 narrowed it. A project written
	 * in another language is one this component can open, edit and save
	 * perfectly well - 3.4 made that true - and could not generate from,
	 * because the rules are about ER1. It still cannot, unless that other
	 * language *derives* from ER1: a derived language adds and may not remove
	 * or rename, so every path a rule walks is still there and what the child
	 * added is simply never read.
	 *
	 * Which is what makes the refusal worth keeping rather than widening. A
	 * language with no ER1 anywhere in its ancestry is still a language these
	 * rules say nothing about, and running them over it produces a component
	 * with empty views and no error.
	 *
	 * @return  MetalanguageEntry
	 *
	 * @throws  \RuntimeException  When the project is written in something else.
	 */
	private function language(): MetalanguageEntry
	{
		$database = $this->getDatabase();
		$binding  = (new ProjectRepository($database))->binding((int) $this->projectId);

		if ($binding === null) {
			throw new \RuntimeException(
				Text::sprintf('COM_EXTENGEN_GENERATE_NO_METALANGUAGE', $this->projectId)
			);
		}

		$entry = Metalanguages::forProject($database, $binding['key'], $binding['version']);

		if ($entry === null) {
			// Bound to a language this site has not got. Its forms are gone, so
			// its reference table is too, and the join would silently reach
			// nothing. Asked before the ancestry, because an ancestry is a walk
			// from an entry and there is no entry to walk from.
			throw new \RuntimeException(
				Text::sprintf(
					'COM_EXTENGEN_GENERATE_METALANGUAGE_MISSING',
					$binding['key'],
					$binding['version'] ?: '?'
				)
			);
		}

		// The language these rules are about, or one it derives from: step 4.5.
		//
		// Until now this compared the key and stopped there, which meant a
		// language built on ER1 was refused for being built on ER1. A derived
		// language adds and may not remove or rename - `AncestryCheck` refuses
		// the import otherwise - so every path a rule for the parent walks is
		// still there, and the nodes the child added are simply never read.
		$ancestry = Metalanguages::catalogue($database)->ancestry();

		foreach ($ancestry->withSelf($entry) as $candidate) {
			if ($candidate->key === RuleDrivenGenerator::LANGUAGE) {
				return $entry;
			}
		}

		throw new \RuntimeException(
			Text::sprintf(
				'COM_EXTENGEN_GENERATE_WRONG_METALANGUAGE',
				$entry->label(),
				RuleDrivenGenerator::LANGUAGE
			)
		);
	}

	/**
	 * Put the generated file set on disk, under the component's output directory.
	 *
	 * @param   FileCollection  $files          What was generated.
	 * @param   Project         $project        The model it generated from.
	 *
	 * @return  void
	 */
	private function write(FileCollection $files, Project $project, string $targetId): void
	{
		$componentName = $project->componentName();
		$generated     = JPATH_ROOT . '/administrator/components/com_extengen/generated/'
			. $componentName . '/' . $targetId;

		// Under the target's own directory since 4.4, both of them. Two
		// targets writing a zip into one folder is a folder where
		// `glob('*.zip')` returns whichever the filesystem felt like -
		// which is what `tools/install-generated.php` does, and it would
		// have handed Joomla a WordPress plugin.
		//
		// And named for the project rather than `com_<project>`. That
		// prefix is Joomla's word for a component and it was sitting in
		// the path of a WordPress plugin - a small thing, and exactly the
		// kind of small thing a second target is for finding.
		$root = $generated . '/' . strtolower($componentName);

		if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
			throw new \RuntimeException('Cannot create ' . $root);
		}

		// Carry back anything somebody wrote into the last run's output.
		// Slots in the model are how custom code is meant to survive; this is
		// the net under that, for edits made in the generated files anyway.
		$this->carryOverEdits($files, $root);

		$writer = new ZipWriter();

		// The archive is the deliverable: it is what somebody installs, and it
		// is the only form in which the output is a single thing that can be
		// handed to Joomla. The version is in its name because a downloads
		// folder full of identically named packages says nothing about which
		// is which, and the one that matters is rarely the newest by date.
		$version = trim((string) ($project->manifest()->version ?? '')) ?: '0.0.0';
		$archive = $generated . '/' . strtolower($componentName) . '-' . $version . '.zip';

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
	 * Merge the previous run's protected regions into the new file set.
	 *
	 * The safety net, not the mechanism. Custom code belongs in the model,
	 * where regenerating cannot touch it; this is for the edit somebody made
	 * directly in a generated file, which otherwise disappears the next time
	 * anyone presses generate. Losing somebody's work without telling them is
	 * the worst thing a generator can do, so a region the new output no
	 * longer has is reported rather than dropped quietly.
	 *
	 * It reads the unpacked tree from the last run, which is the only copy of
	 * the output this component keeps - the zip is the deliverable and may
	 * have been installed and edited somewhere else entirely, where nothing
	 * here can see it.
	 *
	 * @param   FileCollection  $files  What was generated, changed in place.
	 * @param   string          $root   Where the previous run was unpacked.
	 *
	 * @return  void
	 */
	private function carryOverEdits(FileCollection $files, string $root): void
	{
		if (!is_dir($root)) {
			// Nothing has been generated here before.
			return;
		}

		$merger  = new ProtectedRegionMerger(SlotCatalogue::TAG);
		$carried = 0;
		$orphans = [];

		foreach ($files->all() as $path => $contents) {
			$previous = $root . '/' . $path;

			if (!is_file($previous)) {
				continue;
			}

			$existing = (string) file_get_contents($previous);
			$merged   = $merger->merge($existing, $contents);

			foreach ($merger->orphanedRegions() as $id) {
				$orphans[] = $path . ' : ' . $id;
			}

			if ($merged !== $contents) {
				$files->replace($path, $merged);
				$carried++;
			}
		}

		if ($carried > 0) {
			$this->log[] = 'kept hand-written regions in ' . $carried . ' file(s) from the previous run';
		}

		foreach ($orphans as $orphan) {
			// Its content is still in the file on disk and nowhere else, so
			// saying where is the whole of the warning.
			$this->log[] = '<b>not carried over, and only in the previous output: ' .
			htmlspecialchars($orphan, ENT_QUOTES, 'UTF-8') . '</b>';
		}
	}

	/**
	 * The project being generated from.
	 *
	 * @return  Project
	 */
	private function loadProject(): Project
	{
		$project = (new ProjectRepository($this->getDatabase()))->find((int) $this->projectId);

		if ($project === null) {
			throw new \RuntimeException(sprintf('Cannot read project %d.', $this->projectId));
		}

		return $project;
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
