<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Application\WebApplicationInterface;
use Yepr\Component\Extengen\Administrator\Model\GenerateModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Hand over what a generation run made: download it (5.5), or install it here (5.6).
 *
 * **Neither task takes a path.** Both are given a project and a generator and
 * ask the model where that package is, so nothing in a request can point at
 * any other file on the server.
 *
 * @since  1.3.0
 */
class GenerateController extends BaseController
{
    /**
     * Send the generated package to the browser.
     *
     * @since  1.3.0
     */
    public function download(): void
    {
        $this->checkToken('get');
        $this->assertMayGenerate();

        $archive = $this->archive();
        $app     = $this->app;

        // Both the site and the administrator are web applications; a CLI
        // one has nobody to send a file to.
        if (!$app instanceof WebApplicationInterface) {
            throw new \RuntimeException('A download needs a web application.', 500);
        }

        $app->setHeader('Content-Type', 'application/zip', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . basename($archive) . '"', true);
        $app->setHeader('Content-Length', (string) filesize($archive), true);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->sendHeaders();

        readfile($archive);

        $app->close();
    }

    /**
     * Install the generated package on this site: step 5.6.
     *
     * Off unless the component's options say otherwise, and never from the
     * site: a generated extension is code built from whatever is in the model,
     * custom-code slots included, so on a site where people other than the
     * administrator can model, this is a way for them to install code. Every
     * condition the result screen used to decide whether to show the button is
     * checked again here, because a button that is not shown is not a check.
     *
     * @since  1.3.0
     */
    public function install(): void
    {
        $this->checkToken('get');

        if ($this->app->isClient('site')) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        if ((int) ComponentHelper::getParams('com_extengen')->get('allow_install', 0) !== 1) {
            throw new NotAllowed(Text::_('COM_EXTENGEN_GENERATE_INSTALL_DISABLED'), 403);
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_installer')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model     = $this->generateModel();
        $generator = $model->resolveGenerator();

        if ($generator->target !== 'joomla6') {
            throw new \RuntimeException(Text::sprintf('COM_EXTENGEN_GENERATE_INSTALL_NOT_JOOMLA', $generator->targetLabel), 400);
        }

        $archive = $this->archive($model);
        $back    = Route::_('index.php?option=com_extengen&view=projects', false);

        // A copy, because the installer's cleanup deletes the package it was
        // given, and the generated one is still the download.
        $copy = rtrim((string) $this->app->get('tmp_path'), '/\\') . '/extengen-' . bin2hex(random_bytes(6)) . '.zip';

        if (!copy($archive, $copy)) {
            $this->setRedirect($back, Text::_('COM_EXTENGEN_GENERATE_INSTALL_FAILED'), 'error');

            return;
        }

        $package = InstallerHelper::unpack($copy, true);

        if (empty($package['dir'])) {
            if (is_file($copy)) {
                unlink($copy);
            }
            $this->setRedirect($back, Text::_('COM_EXTENGEN_GENERATE_INSTALL_FAILED'), 'error');

            return;
        }

        $installed = Installer::getInstance()->install($package['dir']);

        InstallerHelper::cleanupInstall($copy, $package['extractdir']);

        $this->setRedirect(
            $back,
            Text::sprintf($installed ? 'COM_EXTENGEN_GENERATE_INSTALLED' : 'COM_EXTENGEN_GENERATE_INSTALL_FAILED', basename($archive)),
            $installed ? 'message' : 'error'
        );
    }

    /**
     * Generating, and having what it made, is for people who may use this component.
     *
     * @since  1.3.0
     */
    private function assertMayGenerate(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_extengen')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * The model, told which project and generator the request names.
     *
     * @since  1.3.0
     */
    private function generateModel(): GenerateModel
    {
        /** @var GenerateModel $model */
        $model = $this->getModel('Generate', 'Administrator', ['ignore_request' => true]);
        $input = $this->app->getInput();

        $model->setProjectId($input->getInt('project_id'));
        $model->setGeneratorId((string) $input->getCmd('generator', ''));

        return $model;
    }

    /**
     * The package for the request's project and generator, which must exist.
     *
     * @since  1.3.0
     */
    private function archive(?GenerateModel $model = null): string
    {
        try {
            $archive = ($model ?? $this->generateModel())->archivePath();
        } catch (\RuntimeException) {
            $archive = '';
        }

        if ($archive === '' || !is_file($archive)) {
            throw new \RuntimeException(Text::_('COM_EXTENGEN_GENERATE_NO_PACKAGE'), 404);
        }

        return $archive;
    }
}
