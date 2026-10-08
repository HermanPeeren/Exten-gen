<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The frontend's addresses: step 5.7.
 *
 * Without a router, a site with search-engine-friendly URLs and strict routing
 * - Joomla's defaults - cannot build an address for "edit project 82", and the
 * SEF plugin redirects every such link to the home page. Three views: the
 * list, which a menu item points at, and under it the form a project is
 * edited in and the generate screen, each with its project in the query.
 *
 * @since  1.3.0
 */
class Router extends RouterView
{
    /**
     * @param   SiteApplication  $app   The application.
     * @param   AbstractMenu     $menu  The site's menu.
     *
     * @since   1.3.0
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        $projects = new RouterViewConfiguration('projects');
        $this->registerView($projects);

        // Not keyed by id: a new project has none, and a view keyed by one has
        // no address without it - the add task's redirect came back as the
        // list. Joomla's own frontend editing has a `form` view for the same
        // reason, and the id rides in the query.
        $form = new RouterViewConfiguration('form');
        $form->setParent($projects)->addLayout('edit');
        $this->registerView($form);

        $generate = new RouterViewConfiguration('generate');
        $generate->setParent($projects);
        $this->registerView($generate);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }
}
