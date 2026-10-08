<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\View\Projects;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * My projects: step 5.7.
 *
 * @since  1.3.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The visitor's projects.
     *
     * @var    object[]
     * @since  1.3.0
     */
    public array $items = [];

    /**
     * Whether the visitor may start a new one.
     *
     * @var    bool
     * @since  1.3.0
     */
    public bool $canCreate = false;

    /**
     * @param   string  $tpl  A template file to load. [optional]
     *
     * @return  void
     *
     * @since   1.3.0
     */
    public function display($tpl = null): void
    {
        /** @var \Yepr\Component\Extengen\Site\Model\ProjectsModel $model */
        $model = $this->getModel();

        $this->items     = $model->getItems() ?: [];
        $this->canCreate = $this->getCurrentUser()->authorise('core.create', 'com_extengen');

        parent::display($tpl);
    }
}
