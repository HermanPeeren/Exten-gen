<?php

/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Projects list controller class.
 */
class ProjectsController extends AdminController
{
	/**
     * Constructor.
     *
     * @param   array                         $config   An optional associative array of configuration settings.
     *                                                  Recognized key values include 'name', 'default_task', 'model_path', and
     *                                                  'view_path' (this list is not meant to be comprehensive).
     * @param   MVCFactoryInterface|null      $factory  The factory.
     * @param   CMSApplication|null           $app      The JApplication for the dispatcher
     * @param   Input|null                    $input    Input
	 */
	public function __construct($config = array(), MVCFactoryInterface $factory = null, $app = null, $input = null)
	{
		parent::__construct($config, $factory, $app, $input);
	}

	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The name of the model.
	 * @param   string  $prefix  The prefix for the PHP class name.
	 * @param   array   $config  Array of configuration parameters.
	 *
	 * @return  BaseDatabaseModel
	 */
	public function getModel($name = 'Project', $prefix = '', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Read a LionWeb chunk in as a project.
	 *
	 * A path on this site rather than an upload: JcbInOut writes a blueprint to
	 * disk here, so the two components meet on the filesystem and there is no
	 * temporary file, no MIME guessing and no second copy to keep in step. The
	 * same arrangement Meta-gen uses for a language.
	 *
	 * @return  void
	 *
	 * @since   1.4.0
	 */
	public function importLionweb()
	{
		$this->checkToken();

		$back = 'index.php?option=com_extengen&view=projects';

		if (!$this->app->getIdentity()->authorise('core.create', 'com_extengen')) {
			$this->setRedirect(Route::_($back, false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');

			return;
		}

		$path = trim((string) $this->input->getString('chunk', ''));

		if ($path === '') {
			$this->setRedirect(
				Route::_($back, false),
				Text::_('COM_EXTENGEN_LIONWEB_NO_PATH'),
				'warning'
			);

			return;
		}

		/** @var \Yepr\Component\Extengen\Administrator\Model\LionwebModel $model */
		$model = $this->getModel('Lionweb', '', ['ignore_request' => true]);

		try {
			$converted = $model->convert($model->readChunk($path));
			$id        = $model->store($converted);

			// Said before the success message, because a model that arrived
			// with something missing is still one somebody is about to edit.
			foreach ($converted['diagnostics'] as $diagnostic) {
				$this->app->enqueueMessage(
					$diagnostic['message'],
					$diagnostic['severity'] === 'error' ? 'error' : 'warning'
				);
			}

			$this->setRedirect(
				Route::_('index.php?option=com_extengen&task=project.edit&id=' . $id, false),
				Text::sprintf(
					'COM_EXTENGEN_LIONWEB_IMPORTED',
					$converted['name'],
					$converted['key'],
					$converted['version'],
					$converted['groups']
				)
			);
		} catch (\Throwable $e) {
			$this->setRedirect(Route::_($back, false), $e->getMessage(), 'error');
		}
	}

	/**
	 * Write the selected project out as a LionWeb chunk.
	 *
	 * Sent to the browser rather than left on the site. A file written under
	 * the site would be one more thing to find, to clean up and to get the
	 * permissions right for, and the thing somebody is about to do with a
	 * chunk is give it to another tool.
	 *
	 * @return  void
	 *
	 * @since   1.4.0
	 */
	public function exportLionweb()
	{
		$this->checkToken();

		$back = 'index.php?option=com_extengen&view=projects';
		$ids  = (array) $this->input->get('cid', [], 'array');
		$id   = (int) ($ids[0] ?? 0);

		if ($id === 0) {
			$this->setRedirect(Route::_($back, false), Text::_('JGLOBAL_NO_ITEM_SELECTED'), 'warning');

			return;
		}

		/** @var \Yepr\Component\Extengen\Administrator\Model\LionwebModel $model */
		$model = $this->getModel('Lionweb', '', ['ignore_request' => true]);

		try {
			$written = $model->export($id);
		} catch (\Throwable $e) {
			$this->setRedirect(Route::_($back, false), $e->getMessage(), 'error');

			return;
		}

		// A model that went out with something missing is still one somebody is
		// about to hand to another tool, so it is said rather than swallowed -
		// but it cannot be said over a download, so anything to report sends
		// them back to the list instead of handing over a file they would have
		// to be told about afterwards.
		if ($written['diagnostics'] !== []) {
			foreach ($written['diagnostics'] as $diagnostic) {
				$this->app->enqueueMessage(
					$diagnostic['message'],
					$diagnostic['severity'] === 'error' ? 'error' : 'warning'
				);
			}

			$this->setRedirect(Route::_($back, false));

			return;
		}

		$name = preg_replace('/[^A-Za-z0-9_-]+/', '-', $written['name']) ?: 'project';

		$this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		$this->app->setHeader(
			'Content-Disposition',
			'attachment; filename="' . $name . '.instance.lionweb.json"',
			true
		);
		$this->app->setHeader('Content-Length', (string) \strlen($written['json']), true);
		$this->app->sendHeaders();

		echo $written['json'];

		$this->app->close();
	}
}
