<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\View\Form;

use Yepr\Component\Extengen\Administrator\View\Project\HtmlView as AdministratorHtmlView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One project, edited on the frontend: step 5.7.
 *
 * The administrator's view - the language's tabs, the reference index in the
 * page - without the administrator's toolbar. The layout puts its own Save
 * and Close buttons under the form instead.
 *
 * @since  1.3.0
 */
class HtmlView extends AdministratorHtmlView
{
    /**
     * No toolbar on the frontend.
     *
     * @return  void
     *
     * @since   1.3.0
     */
    protected function addToolbar()
    {
    }
}
