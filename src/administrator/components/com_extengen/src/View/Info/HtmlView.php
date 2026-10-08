<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\View\Info;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What Exten-gen, Meta-gen and Gen-gen are, and how they fit together.
 *
 * The text is in the language file and the layout in `tmpl/info/default.php`.
 *
 * @since  1.3.3
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @param   string  $tpl  A template file to load. [optional]
     *
     * @return  void
     *
     * @since   1.3.3
     */
    public function display($tpl = null): void
    {
        ToolbarHelper::title(Text::_('COM_EXTENGEN_MANAGER_INFO'), 'info-circle');

        parent::display($tpl);
    }
}
