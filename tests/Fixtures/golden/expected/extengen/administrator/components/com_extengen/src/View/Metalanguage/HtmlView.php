<?php
/**
 * @package    Extengen
 * @subpackage Extengen
 * @version    1.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL 3.0
 */

namespace Yepr\Component\Extengen\Administrator\View\Metalanguage;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Component\ComponentHelper;
use Exception;

use Yepr\Component\Extengen\Administrator\Model\MetalanguageModel;

class HtmlView extends BaseHtmlView
{
	/**
	 * The Form object
	 *
	 * @var    Form
	 */
	protected $form;

	/**
	 * The active item
	 *
	 * @var    object (this is actually a Table object)
	 */
	protected $item;

	/**
	 * Display the view.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
     * @return  void
     * @throws  Exception
	 */
	public function display($tpl = null)
	{
		/** @var MetalanguageModel $model */
		$model       = $this->getModel();

		$this->item  = $model->getItem();
		$this->form  = $model->getForm();

		// todo: If we are forcing a language in modal (used for associations).

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Set up Joomla's toolbar.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	protected function addToolbar(): void
	{
		Factory::getApplication()->getInput()->set('hidemainmenu', true);

		$user = $this->getCurrentUser();
		$userId = $user->id;

		$isNew = ($this->item->id == 0);

		ToolbarHelper::title($isNew ? Text::_('COM_EXTENGEN_MANAGER_METALANGUAGE_NEW') : Text::_('COM_EXTENGEN_MANAGER_METALANGUAGE_EDIT'), 'address metalanguage');
		// todo: choose icon in model

		// Since we don't track these assets at the item level, use the category id. todo: categories & access control
		// $canDo = ContentHelper::getActions('com_extengen', 'category', $this->item->catid);

		// ACCESS CONTROL for now is on the extension-level...

		// Build the actions for new and existing records.
		if ($isNew) {
			// For new records, check the create permission.
			//if ($isNew && (count($user->getAuthorisedCategories('com_extengen', 'core.create')) > 0)) {
			if ($isNew) {
				ToolbarHelper::apply('metalanguage.apply');
				ToolbarHelper::saveGroup(
					[
						['save', 'metalanguage.save'],
						['save2new', 'metalanguage.save2new']
					],
					'btn-success'
				);
			}

			ToolbarHelper::cancel('metalanguage.cancel');
		} else {
			// Since it's an existing record, check the edit permission, or fall back to edit own if the owner.
			//$itemEditable = $canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_by == $userId);
			$toolbarButtons = [];

			// Can't save the record if it's not editable
			//if ($itemEditable) {
				ToolbarHelper::apply('metalanguage.apply');
				$toolbarButtons[] = ['save', 'metalanguage.save'];

				// We can save this record, but check the create permission to see if we can return to make a new one.
				//if ($canDo->get('core.create')) {
					$toolbarButtons[] = ['save2new', 'metalanguage.save2new'];
				//}
			//}

			// If checked out, we can still save
			//if ($canDo->get('core.create')) {
				$toolbarButtons[] = ['save2copy', 'metalanguage.save2copy'];
			//}

			ToolbarHelper::saveGroup(
				$toolbarButtons,
				'btn-success'
			);

			if (Associations::isEnabled() && ComponentHelper::isEnabled('com_associations')) {
				ToolbarHelper::custom('metalanguage.editAssociations', 'contract', 'contract', 'JTOOLBAR_ASSOCIATIONS', false, false);
			}

			ToolbarHelper::cancel('metalanguage.cancel', 'JTOOLBAR_CLOSE');
		}

		// Todo: Help-pages
		//ToolbarHelper::divider();
		//ToolbarHelper::inlinehelp();
		//ToolbarHelper::help('', false, 'http://example.org');
	}

}
