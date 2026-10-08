<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Yepr\Component\Extengen\Site\Helper\Ownership;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The frontend's screens: the user's projects, one project, and generating it: step 5.7.
 *
 * @since  1.3.0
 */
class DisplayController extends BaseController
{
    /**
     * @var    string
     * @since  1.3.0
     */
    protected $default_view = 'projects';

    /**
     * @param   boolean  $cachable   Not used: every screen here is about the visitor's own projects.
     * @param   array    $urlparams  Not used.
     *
     * @return  static
     *
     * @since   1.3.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $input = $this->input;
        $view  = $input->getCmd('view', $this->default_view);
        $user  = $this->app->getIdentity();

        // A project is opened through the edit task, which checks it out and
        // remembers it as being edited; a link straight to the layout skips
        // both. Generating names its project in `project_id`.
        if ($view === 'form') {
            $id = $input->getInt('id');

            if ($id > 0 && !$this->checkEditId('com_extengen.edit.project', $id)) {
                throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
            }
        }

        if ($view === 'generate') {
            Ownership::assertMayOpen($input->getInt('project_id'), $user);
        }

        return parent::display(false, $urlparams);
    }
}
