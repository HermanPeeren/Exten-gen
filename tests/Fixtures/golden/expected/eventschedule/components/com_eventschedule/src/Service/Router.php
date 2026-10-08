<?php
/**
 * @package    EventSchedule
 * @subpackage eventschedule
 * @version    1.0.1
 *
 * @copyright  Herman Peeren, Yepr
 * @license    GPL vs3+
 */

namespace Yepr\Component\eventschedule\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * The addresses of this component's site pages.
 *
 * The component tells Joomla it routes its own links, so it must have a
 * router: without one, Joomla cannot build a link to any of its pages, and a
 * menu item pointing at one links to the home page instead.
 *
 * A list page is a view of its own; a details page is keyed by the record's
 * id, under the list page that links to it.
 */
class Router extends RouterView
{
	/**
	 * @param   SiteApplication  $app   The application.
	 * @param   AbstractMenu     $menu  The site's menu.
	 */
	public function __construct(SiteApplication $app, AbstractMenu $menu)
	{
		$views = [];

		$views['schedule'] = new RouterViewConfiguration('schedule');
		$this->registerView($views['schedule']);

		parent::__construct($app, $menu);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}
}
