<?php
/**
 * @package    BalloonLedger
 * @subpackage BalloonLedger
 * @version    0.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL3
 */

namespace Yepr\Component\BalloonLedger\Administrator\Controller;

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
 * Party controller class.
 *
 * @version 0.1.0
 */
class PartyController extends FormController
{
	/**
	  * Override constructor to indicate the right list-view
	  * (especially with different names for views than standard entity-names)
	  *
	  * Alternatieve: Add $this->applyReturnUrl(); See Nic's PostController + ReturnURLAware mixin
	  */
	public function __construct($config = array(), MVCFactoryInterface $factory = null, $app = null, $input = null)
	{
		$this->view_list = 'parties';
		parent::__construct($config, $factory, $app, $input);
	}

/**
	 * Method to run batch operations.
	 *
	 * @param   object  $model  The model.
	 *
	 * @return  boolean   True if successful, false otherwise and internal error is set.
	 *
	 * @since    0.1.0
	 */
	public function batch($model = null)
	{
		$this->checkToken();

		$model = $this->getModel('Party', 'Administrator', []);

		// Preset the redirect
		$this->setRedirect(Route::_('index.php?option=com_balloonledger&view=parties' . $this->getRedirectToListAppend(), false));

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
