<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The model the `form` view asks for by its own name: step 5.7.
 *
 * The frontend edits a project in a view called `form`, as Joomla's own
 * frontend editing does, because a view keyed by id has no address for a
 * project that has no id yet. A view's model is found by the view's name, so
 * this is the project model under that name, and nothing else.
 *
 * @since  1.3.0
 */
class FormModel extends ProjectModel
{
    /**
     * The form's name stays the project's, so the edit data Joomla keeps in
     * the session between a failed save and the form is found under one key.
     *
     * @var    string
     * @since  1.3.0
     */
    protected $name = 'project';
}
