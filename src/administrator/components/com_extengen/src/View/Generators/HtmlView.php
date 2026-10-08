<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\View\Generators;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The generators this site can run, and the import: step 5.4.
 *
 * This page said "TODO" from 0.8 until 1.3. The built-in generators are one
 * per target; the imported ones come from Gen-gen. The page is shaped like the
 * metalanguages page, because importing a package is the same gesture.
 *
 * @since  1.3.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var GeneratorEntry[]
     */
    public array $items = [];

    /**
     * @param   string  $tpl  A template file to load. [optional]
     *
     * @since   1.3.0
     */
    public function display($tpl = null): void
    {
        /** @var \Yepr\Component\Extengen\Administrator\Model\GeneratorsModel $model */
        $model = $this->getModel();

        $this->items = $model->getItems();

        ToolbarHelper::title(Text::_('COM_EXTENGEN_MANAGER_GENERATORS'), 'cogs');

        parent::display($tpl);
    }
}
