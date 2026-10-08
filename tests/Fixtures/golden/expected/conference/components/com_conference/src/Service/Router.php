<?php
/**
 * @package    MyConference
 * @subpackage Conference
 * @version    1.0.0
 *
 * @copyright  Herman Peeren - Yepr - 2023
 * @license    GPL 3.0
 */

namespace Yepr\Component\Conference\Site\Service;

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

		$views['participants'] = new RouterViewConfiguration('participants');
		$this->registerView($views['participants']);

		$views['talks'] = new RouterViewConfiguration('talks');
		$this->registerView($views['talks']);

		$views['rooms'] = new RouterViewConfiguration('rooms');
		$this->registerView($views['rooms']);

		$views['program'] = new RouterViewConfiguration('program');
		$this->registerView($views['program']);

		$views['participant'] = new RouterViewConfiguration('participant');
		$views['participant']->setKey('id')->setParent($views['participants']);
		$this->registerView($views['participant']);

		$views['talk'] = new RouterViewConfiguration('talk');
		$views['talk']->setKey('id')->setParent($views['talks']);
		$this->registerView($views['talk']);

		$views['room'] = new RouterViewConfiguration('room');
		$views['room']->setKey('id')->setParent($views['rooms']);
		$this->registerView($views['room']);

		$views['session'] = new RouterViewConfiguration('session');
		$views['session']->setKey('id')->setParent($views['program']);
		$this->registerView($views['session']);

		parent::__construct($app, $menu);

		$this->attachRule(new MenuRules($this));
		$this->attachRule(new StandardRules($this));
		$this->attachRule(new NomenuRules($this));
	}

	/**
	 * The segment for one participant: its id.
	 *
	 * @param   string  $id     The id.
	 * @param   array   $query  The request being built.
	 *
	 * @return  array
	 */
	public function getParticipantSegment($id, $query)
	{
		return [(int) $id => (string) (int) $id];
	}

	/**
	 * The id of one participant, from its segment.
	 *
	 * @param   string  $segment  The segment.
	 * @param   array   $query    The request being parsed.
	 *
	 * @return  int|false
	 */
	public function getParticipantId($segment, $query)
	{
		return ctype_digit((string) $segment) ? (int) $segment : false;
	}

	/**
	 * The segment for one talk: its id.
	 *
	 * @param   string  $id     The id.
	 * @param   array   $query  The request being built.
	 *
	 * @return  array
	 */
	public function getTalkSegment($id, $query)
	{
		return [(int) $id => (string) (int) $id];
	}

	/**
	 * The id of one talk, from its segment.
	 *
	 * @param   string  $segment  The segment.
	 * @param   array   $query    The request being parsed.
	 *
	 * @return  int|false
	 */
	public function getTalkId($segment, $query)
	{
		return ctype_digit((string) $segment) ? (int) $segment : false;
	}

	/**
	 * The segment for one room: its id.
	 *
	 * @param   string  $id     The id.
	 * @param   array   $query  The request being built.
	 *
	 * @return  array
	 */
	public function getRoomSegment($id, $query)
	{
		return [(int) $id => (string) (int) $id];
	}

	/**
	 * The id of one room, from its segment.
	 *
	 * @param   string  $segment  The segment.
	 * @param   array   $query    The request being parsed.
	 *
	 * @return  int|false
	 */
	public function getRoomId($segment, $query)
	{
		return ctype_digit((string) $segment) ? (int) $segment : false;
	}

	/**
	 * The segment for one session: its id.
	 *
	 * @param   string  $id     The id.
	 * @param   array   $query  The request being built.
	 *
	 * @return  array
	 */
	public function getSessionSegment($id, $query)
	{
		return [(int) $id => (string) (int) $id];
	}

	/**
	 * The id of one session, from its segment.
	 *
	 * @param   string  $segment  The segment.
	 * @param   array   $query    The request being parsed.
	 *
	 * @return  int|false
	 */
	public function getSessionId($segment, $query)
	{
		return ctype_digit((string) $segment) ? (int) $segment : false;
	}
}
