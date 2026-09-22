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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Importing and removing metalanguages: step 3.4.
 *
 * @since  1.1.0
 */
class MetalanguagesController extends BaseController
{
    /**
     * Install an uploaded metalanguage package.
     *
     * @return  void
     *
     * @since  1.1.0
     */
    public function import(): void
    {
        $this->checkToken();
        $this->assertAllowed();

        $app  = $this->app;
        $file = $app->getInput()->files->get('package', null, 'raw');

        $this->setRedirect(Route::_('index.php?option=com_extengen&view=metalanguages', false));

        if (!\is_array($file) || ($file['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
            $app->enqueueMessage(Text::_('COM_EXTENGEN_METALANGUAGE_NO_FILE'), 'error');

            return;
        }

        // The uploaded temp file, read where PHP put it. Not moved anywhere
        // first: an import that is refused should leave nothing behind, and
        // the only thing that writes to the site is the importer, after it has
        // decided the package is sound.
        $importer = Metalanguages::importer($this->getDatabaseDriver(), JPATH_ROOT);

        try {
            $entry = $importer->import((string) $file['tmp_name']);
        } catch (\RuntimeException $e) {
            $app->enqueueMessage($e->getMessage(), 'error');

            return;
        }

        $app->enqueueMessage(
            Text::sprintf('COM_EXTENGEN_METALANGUAGE_IMPORTED', $entry->label()),
            'message'
        );
    }

    /**
     * Forget an imported language.
     *
     * The row goes and the files stay. Deleting a tree under the site root
     * because a form was posted is a different kind of operation, and a
     * language nothing is written in is a few kilobytes of XML - whereas a
     * language something *is* written in, deleted by mistake, is a project
     * that will not open. Removing the row is enough to take it out of the
     * dropdown and is undone by importing the package again.
     *
     * @return  void
     *
     * @since  1.1.0
     */
    public function remove(): void
    {
        $this->checkToken();
        $this->assertAllowed();

        $app      = $this->app;
        $database = $this->getDatabaseDriver();
        $id       = $app->getInput()->getInt('id', 0);

        $this->setRedirect(Route::_('index.php?option=com_extengen&view=metalanguages', false));

        $query = $database->getQuery(true)
            ->delete($database->quoteName(Metalanguages::TABLE))
            ->where($database->quoteName('id') . ' = :id')
            ->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER);

        $database->setQuery($query)->execute();

        $app->enqueueMessage(Text::_('COM_EXTENGEN_METALANGUAGE_REMOVED'), 'message');
    }

    /**
     * @since  1.1.0
     */
    private function assertAllowed(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_extengen')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    /**
     * @since  1.1.0
     */
    private function getDatabaseDriver(): DatabaseInterface
    {
        return \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
    }
}
