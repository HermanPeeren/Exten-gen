<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\View\Metalanguages;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Yepr\Component\Extengen\Administrator\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The metalanguages this site can write a project in: step 3.4.
 *
 * @since  1.1.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Every language, the built-in first.
     *
     * @var MetalanguageEntry[]
     *
     * @since  1.1.0
     */
    public array $items = [];

    /**
     * How many projects are written in each, keyed by binding.
     *
     * @var array<string, int>
     *
     * @since  1.1.0
     */
    public array $counts = [];

    /**
     * @param   string|null  $tpl  The layout.
     *
     * @return  void
     *
     * @since  1.1.0
     */
    public function display($tpl = null): void
    {
        /** @var \Yepr\Component\Extengen\Administrator\Model\MetalanguagesModel $model */
        $model = $this->getModel();

        $this->items  = $model->getItems();
        $this->counts = $model->projectCounts();

        ToolbarHelper::title(Text::_('COM_EXTENGEN_MANAGER_METALANGUAGES'), 'puzzle');

        parent::display($tpl);
    }
}
