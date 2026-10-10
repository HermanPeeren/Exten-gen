<?php
/**
 * @package    BalloonLedger
 * @subpackage BalloonLedger
 * @version    0.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL3
 */

defined('_JEXEC') or die;

/** @var \Yepr\Component\BalloonLedger\Administrator\View\Journal\HtmlView $this */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

$app = Factory::getApplication();
$input = $app->getInput(); // todo: really???

$assoc = Associations::isEnabled();

$this->ignore_fieldsets = ['item_associations']; // todo: associations
$this->useCoreUI = true;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
	->useScript('form.validate');
	//->useScript('com_balloonledger.admin-balloonledger-letter');// todo: what is that letter-script? Is that correct here?

$layout  = 'edit';
$tmpl = $input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>
<form action="<?php echo Route::_('index.php?option=com_balloonledger&view=journal&layout=' . $layout . $tmpl . '&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="journal-form" class="form-validate">

	<?php // todo: add the general link-field, like the name or title, on top of the tabset ?>
    <div>
		<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details']); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', empty($this->item->id) ? Text::_('COM_BALLOONLEDGER_NEW_JOURNAL') : Text::_('COM_BALLOONLEDGER_EDIT_JOURNAL')); ?>
        <div class="row">
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-6">

                                                <?php echo $this->getForm()->renderField('code'); ?>
                                                <?php echo $this->getForm()->renderField('name'); ?>
                                                <?php echo $this->getForm()->renderField('journal_type'); ?>
                                                <?php echo $this->getForm()->renderField('gapless'); ?>
                        
                        <?php //echo $this->form->getInput('id'); ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
						<?php //echo LayoutHelper::render('joomla.edit.global', $this); ?>
                    </div>
                </div>
            </div>
        </div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php if ($assoc) : ?>
			<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'associations', Text::_('JGLOBAL_FIELDSET_ASSOCIATIONS')); ?>
			<?php //echo $this->loadTemplate('associations'); ?>
			<?php echo HTMLHelper::_('uitab.endTab'); ?>
		<?php endif; ?>

		<?php //echo LayoutHelper::render('joomla.edit.params', $this); ?>

		<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING')); ?>
        <div class="row">
            <div class="col-md-6">
                <!--  <fieldset id="fieldset-publishingdata" class="options-form">-->
                <!-- <legend><?php /*echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); */?></legend>-->
                <div>
                    <?php //echo LayoutHelper::render('joomla.edit.publishingdata', $this); ?>
                </div>
                <!--/fieldset>-->
            </div>
        </div>
		<?php echo HTMLHelper::_('uitab.endTab'); ?>

		<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>
    <input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
