<?php
/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
// A module now, so that `composer test-js` can import the rule it
// registers - which is the same rule LetterRule.php applies server side.
$this->getDocument()->getWebAssetManager()->useScript('com_extengen.letter');
// <extengen-reference>, and the reference index the view put in the page.
// <yepr-reference>, and the reference index the view put in the page.
//
// The script is the shared library's now: Exten-gen, Meta-gen and Gen-gen all
// edit models with reference dropdowns, and this component was carrying the
// mechanism for all three. A library's asset file is not registered the way
// the active component's is, so it is asked for by name.
$wa = $this->getDocument()->getWebAssetManager();

$wa->getRegistry()->addExtensionRegistryFile('lib_yepr_gen');
$wa->useScript('lib_yepr_gen.reference');

$app = Factory::getApplication();
$input = $app->getInput();

$assoc = Associations::isEnabled();

$this->ignore_fieldsets = array('item_associations');
$this->useCoreUI = true;

// In case of modal
$isModal = $input->get('layout') == 'modal' ? true : false;
$layout  = $isModal ? 'modal' : 'edit';
$tmpl    = $isModal || $input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>
<form action="<?php echo Route::_('index.php?option=com_extengen&layout=' . $layout . $tmpl . '&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="project-form" class="form-validate">

    <?php echo $this->getForm()->renderField('name'); ?>
    <?php echo $this->getForm()->renderField('metalanguage'); ?>

	<?php /*
		One tab per fieldset the project's language declares, in its order:
		step 5.2. ER1 declares entities, pages and extensions, which are the
		three tabs Extengen had - but they come from the language now, not
		from field names written in here, so a language with other groups gets
		other tabs. Fields in no group are on one tab named after the language.

		No tabs at all when the language is not on this site: getForm() has
		said so already, and there is nothing to render the model with.
	*/ ?>
	<?php if ($this->tabs !== null) : ?>
	<?php /* The ERD is an administrator view, so the frontend has no button for it. */ ?>
	<?php $erdTab = $app->isClient('administrator') ? $this->tabs->tabOf('datamodel') : null; ?>
	<div>
		<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => $this->tabs->first(), 'recall' => true)); ?>

		<?php foreach ($this->tabs->tabs as $id => $tab) : ?>
			<?php echo HTMLHelper::_('uitab.addTab', 'myTab', $id, Text::_($tab['label'])); ?>

			<?php /*
				The ERD draws `datamodel`, which is ER1's word for the entities,
				so the button goes wherever that field is.
			*/ ?>
			<?php if ($id === $erdTab && (int) $this->item->id > 0) : ?>
				<div class="mb-3">
					<a class="btn btn-info" data-bs-toggle="modal" href="#ERDModal">
						<?php echo Text::_('COM_EXTENGEN_BUTTON_ERD'); ?>
					</a>
				</div>
			<?php endif; ?>

			<div class="row">
				<div class="col-md-12">
					<?php foreach ($tab['fields'] as $name) : ?>
						<?php if (!\in_array($name, $this->chromeFields, true)) : ?>
							<?php echo $this->getForm()->renderField($name); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php echo HTMLHelper::_('uitab.endTab'); ?>
		<?php endforeach; ?>

		<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
	</div>

	<?php foreach ($this->tabs->hidden as $name) : ?>
		<?php if (!\in_array($name, $this->chromeFields, true)) : ?>
			<?php echo $this->getForm()->getInput($name); ?>
		<?php endif; ?>
	<?php endforeach; ?>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php
if ($app->isClient('administrator') && ($this->item->id) > 0)
{
	echo HTMLHelper::_(
		'bootstrap.renderModal',
		'ERDModal',
		array(
			'title'  => Text::_('COM_EXTENGEN_BUTTON_ERD'),
			'url' => Uri::root() . "administrator/index.php?option=com_extengen&view=ERD&tmpl=component&project_id=" .  $this->item->id,
			'height' => "700",
			'width' => "700"
		)
    ); // todo: adjust height and width to screen
}
 ?>