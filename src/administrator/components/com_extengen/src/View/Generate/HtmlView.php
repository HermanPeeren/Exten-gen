<?php

/**
 * @package     Extesion Generator

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\View\Generate;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorEntry;

/**
 * Generate a project: choose a generator, run it, download what it made.
 *
 * Until 5.5 this ran the default target as soon as it was opened. Now the
 * Generate button opens the chooser, which lists the generators written for
 * the project's language, and running one ends with a download link instead
 * of a path on the server. A request that names a generator (`&generator=`)
 * or a target (`&target=`, since 4.4) still generates straight away.
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * The project being generated.
	 *
	 * @var int
	 */
	public int $projectId = 0;

	/**
	 * The generators the chooser offers. Empty on the result.
	 *
	 * @var GeneratorEntry[]
	 */
	public array $generators = [];

	/**
	 * What the run wrote, line by line. Null on the chooser.
	 *
	 * @var ?string[]
	 */
	public ?array $log = null;

	/**
	 * Why the run stopped, when it did.
	 *
	 * @var string
	 */
	public string $error = '';

	/**
	 * The generator the run used.
	 *
	 * @var ?GeneratorEntry
	 */
	public ?GeneratorEntry $generator = null;

	/**
	 * Where the package can be downloaded from, or '' when there is none.
	 *
	 * @var string
	 */
	public string $downloadUrl = '';

	/**
	 * Where the package can be installed on this site from, or '' when that is not allowed: step 5.6.
	 *
	 * @var string
	 */
	public string $installUrl = '';

	/**
	 * Method to display the view.
	 *
	 * @param   string  $tpl  A template file to load. [optional]
	 *
	 * @return  void
	 */
	public function display($tpl = null): void
	{
		/** @var \Yepr\Component\Extengen\Administrator\Model\GenerateModel $model */
		$model = $this->getModel();
		$input = Factory::getApplication()->getInput();

		$this->projectId = $input->getInt('project_id');
		$model->setProjectId($this->projectId);

		$generatorId = (string) $input->getCmd('generator', '');
		$targetId    = (string) $input->getCmd('target', '');

		$model->setGeneratorId($generatorId);
		$model->setTargetId($targetId);

		if ($generatorId === '' && $targetId === '') {
			$this->generators = $model->generators();

			if ($this->generators === []) {
				// Nothing to choose from is a refusal, said here rather than one
				// click later: the project's language is not one any generator on
				// this site is written for.
				$this->error = $model->refusal();
			}

			$this->setLayout('choose');
			parent::display($tpl);

			return;
		}

		try {
			$model->generate();
		} catch (\RuntimeException $e) {
			$this->error = $e->getMessage();
		}

		$this->log       = $model->log;
		$this->generator = $model->generator;

		if ($this->error === '' && $model->archive !== '' && is_file($model->archive)) {
			$link = 'index.php?option=com_extengen&task=generate.download&project_id=' . $this->projectId
				. '&generator=' . urlencode($model->generator->id ?? '') . '&' . Session::getFormToken() . '=1';

			$this->downloadUrl = Route::_($link, false);

			if ($this->mayInstall()) {
				$this->installUrl = Route::_(str_replace('generate.download', 'generate.install', $link), false);
			}
		}

		$this->setLayout('default');
		parent::display($tpl);
	}

	/**
	 * Whether the result may offer to install the package on this site: step 5.6.
	 *
	 * Three conditions, all checked again by the controller task: the option is
	 * on, the package is a Joomla extension, and the user may install
	 * extensions. The option is off by default, and its description says why.
	 *
	 * @return  bool
	 */
	private function mayInstall(): bool
	{
		return (int) ComponentHelper::getParams('com_extengen')->get('allow_install', 0) === 1
			&& $this->generator !== null
			&& $this->generator->target === 'joomla6'
			&& Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_installer');
	}
}
