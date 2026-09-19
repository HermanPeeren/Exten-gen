<?php
/**
 * @package    EventSchedule
 * @subpackage eventschedule
 * @version    1.0.1
 *
 * @copyright  Herman Peeren, Yepr
 * @license    GPL vs3+
 */

namespace Yepr\Component\eventschedule\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

\defined('_JEXEC') or die;

class SpeakersController extends AdminController
{


	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The name of the model.
	 * @param   string  $prefix  The prefix for the PHP class name.
	 * @param   array   $config  Array of configuration parameters.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 */
	public function getModel($name = 'Speakers', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	public function checkToken($method = 'request', $redirect = true)
	{
		return parent::checkToken($method, $redirect);
	}
}
