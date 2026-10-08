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
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogueAwareInterface;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogueAwareTrait;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Importing a generator from Gen-gen, and forgetting one: step 5.4.
 *
 * The same shape as the metalanguage import, because it is the same gesture.
 * The package is read where PHP put the upload and nothing is written until it
 * has been checked, so a refused import leaves nothing behind.
 *
 * The catalogue is handed over by the component's MVC factory since 1.4.
 *
 * @since  1.3.0
 */
class GeneratorsController extends BaseController implements GeneratorCatalogueAwareInterface
{
    use GeneratorCatalogueAwareTrait;

    /**
     * Import an uploaded generator package.
     *
     * @since  1.3.0
     */
    public function import(): void
    {
        $this->checkToken();
        $this->assertAllowed();

        $app  = $this->app;
        $file = $app->getInput()->files->get('package', null, 'raw');

        $this->setRedirect(Route::_('index.php?option=com_extengen&view=generators', false));

        if (!\is_array($file) || ($file['error'] ?? \UPLOAD_ERR_NO_FILE) !== \UPLOAD_ERR_OK) {
            $app->enqueueMessage(Text::_('COM_EXTENGEN_GENERATOR_NO_FILE'), 'error');

            return;
        }

        try {
            $entry = $this->getGeneratorCatalogue()->import((string) $file['tmp_name']);
        } catch (\RuntimeException $e) {
            $app->enqueueMessage($e->getMessage(), 'error');

            return;
        }

        $app->enqueueMessage(Text::sprintf('COM_EXTENGEN_GENERATOR_IMPORTED', $entry->label(), $entry->ruleCount), 'message');
    }

    /**
     * Forget an imported generator. A built-in one has no row to remove.
     *
     * @since  1.3.0
     */
    public function remove(): void
    {
        $this->checkToken('get');
        $this->assertAllowed();

        $this->getGeneratorCatalogue()->remove($this->app->getInput()->getInt('id', 0));

        $this->setRedirect(
            Route::_('index.php?option=com_extengen&view=generators', false),
            Text::_('COM_EXTENGEN_GENERATOR_REMOVED')
        );
    }

    /**
     * Importing a generator changes what everybody on this site can generate.
     *
     * @since  1.3.0
     */
    private function assertAllowed(): void
    {
        if (!$this->app->getIdentity()->authorise('core.admin', 'com_extengen')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
