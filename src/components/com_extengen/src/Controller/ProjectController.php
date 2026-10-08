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

use Joomla\CMS\MVC\Controller\FormController;
use Yepr\Component\Extengen\Site\Helper\Ownership;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Making and editing a project on the frontend: step 5.7.
 *
 * Joomla's own form controller - add, edit, save, cancel, the check-out and
 * the edit list - with the two questions it asks answered for this component:
 * may this person start a project (`core.create`), and may they change this
 * one (their own, with `core.edit.own`, or anybody's with `core.edit`).
 *
 * @since  1.3.0
 */
class ProjectController extends FormController
{
    /**
     * @var    string
     * @since  1.3.0
     */
    protected $view_list = 'projects';

    /**
     * @var    string
     * @since  1.3.0
     */
    protected $view_item = 'form';

    /**
     * @param   array  $data  Not used.
     *
     * @return  boolean
     *
     * @since   1.3.0
     */
    protected function allowAdd($data = [])
    {
        return $this->app->getIdentity()->authorise('core.create', 'com_extengen');
    }

    /**
     * @param   array   $data  The record, of which only the id is read.
     * @param   string  $key   The name of the key.
     *
     * @return  boolean
     *
     * @since   1.3.0
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        return Ownership::mayOpen((int) ($data[$key] ?? 0), $this->app->getIdentity());
    }
}
