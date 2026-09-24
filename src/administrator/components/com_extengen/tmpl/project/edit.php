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

	<div>
		<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'details')); ?>

		<?php if ($this->metalanguage === null) : ?>

			<?php /*
				The language this project is written in is not on this site, so
				there is nothing to render its model with. getForm() has said so
				already; this is the tab not being there.
			*/ ?>

		<?php elseif (!$this->metalanguage->isBuiltIn()) : ?>

			<?php /*
				A project written in an imported language renders whatever that
				language's root form holds, because nothing here knows what it
				holds. The three tabs below name `datamodel`, `pages` and
				`extensions` - ER1's own fields - and a template that renders a
				model by naming its fields can only ever edit one language.

				3.5 turns ER1 into a package too, at which point the branch
				goes and this is the only path.
			*/ ?>
			<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'entities', $this->metalanguage->label()); ?>
			<div class="row">
				<div class="col-md-12">
					<?php foreach ($this->getForm()->getGroup('') as $field) : ?>
						<?php if (!\in_array($field->fieldname, $this->chromeFields, true)) : ?>
							<?php echo $field->renderField(); ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php else : ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'entities', Text::_('COM_EXTENGEN_HEADING_ENTITIES')); ?>

        <?php if (($this->item->id)>0): ?>
        <div class="row">
            <div class="col-md-12 btns">
                <a class="btn btn-info"  data-bs-toggle="modal"  href="#ERDModal">
				<?php echo Text::_('COM_EXTENGEN_BUTTON_ERD'); ?>
                </a>
                <p>&nbsp;</p>
            </div>
        </div>
        <?php endif; ?>

		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="col-md-12">
						<?php echo $this->getForm()->renderField('datamodel'); ?>
					</div>
				</div>
			</div>
		</div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'pages', Text::_('COM_EXTENGEN_HEADING_PAGES')); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-12">
                        <?php echo $this->getForm()->renderField('pages'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'pages', Text::_('COM_EXTENGEN_HEADING_EXTENSIONS')); ?>
        <div class="row">
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-12">
                        <?php echo $this->getForm()->renderField('extensions'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>


		<?php endif; ?>

		<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
	</div>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php
if (($this->item->id)>0)
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