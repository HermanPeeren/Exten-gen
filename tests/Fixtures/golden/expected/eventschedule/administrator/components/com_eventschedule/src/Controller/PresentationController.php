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

defined('_JEXEC') or die;

//TODO: only add use-clauses when needed; now only a few are used
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Input\Input;

/**
 * Presentation controller class.
 *
 * @version 1.0.1
 */
class PresentationController extends FormController
{
	/**
	  * Override constructor to indicate the right list-view
	  * (especially with different names for views than standard entity-names)
	  *
	  * Alternatieve: Add $this->applyReturnUrl(); See Nic's PostController + ReturnURLAware mixin
	  */
	public function __construct($config = array(), MVCFactoryInterface $factory = null, $app = null, $input = null)
	{
		$this->view_list = 'presentations';
		parent::__construct($config, $factory, $app, $input);
	}

/**
	 * Method to run batch operations.
	 *
	 * @param   object  $model  The model.
	 *
	 * @return  boolean   True if successful, false otherwise and internal error is set.
	 *
	 * @since    1.0.1
	 */
	public function batch($model = null)
	{
		$this->checkToken();

		$model = $this->getModel('Presentation', 'Administrator', []);

		// Preset the redirect
		$this->setRedirect(Route::_('index.php?option=com_eventschedule&view=presentations' . $this->getRedirectToListAppend(), false));

		return parent::batch($model);
	}

	public function edit($key = null, $urlVar = null)
	{
		// Joomla 4.1.1 and later will only allow cid as a POST variable. We need to use it with GET as well.
		$cid = (array) $this->input->get('cid', [], 'int');

		if (!empty($cid))
		{
			$this->input->post->set('cid', $cid);
		}

		return parent::edit($key, $urlVar);
	}

    // TODO: allowAdd, allowEdit etc.

}
