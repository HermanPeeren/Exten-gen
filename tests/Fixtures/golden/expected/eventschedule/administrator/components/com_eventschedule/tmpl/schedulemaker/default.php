<?php
/**
 * @package    EventSchedule
 * @subpackage eventschedule
 * @version    1.0.1
 *
 * @copyright  Herman Peeren, Yepr
 * @license    GPL vs3+
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Session\Session;

/** @var \Yepr\Component\eventschedule\Administrator\View\Schedulemaker\HtmlView $this */

$canChange = true; // todo: user permissions
$assoc = Associations::isEnabled();
$listOrder = $this->escape($this->state->get('list.ordering')); // todo: ordering...
$listDirn  = $this->escape($this->state->get('list.direction'));
//$saveOrder = $listOrder == 'a.ordering';
$saveOrder = false;

if ($saveOrder && !empty($this->items)) {
	$saveOrderingUrl = 'index.php?option=com_eventschedule&task=schedulemaker.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
}
?>
<form action="<?php echo Route::_('index.php?option=com_eventschedule&view=schedulemaker'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
				<?php if (empty($this->items)) : ?>
                    <div class="alert alert-warning">
						<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
				<?php else : ?>
                    <table class="table" id="schedulemakerList">
                        <caption class="visually-hidden">
							<?php echo Text::_('COM_EVENTSCHEDULE_SCHEDULEMAKER_TABLE_CAPTION'); ?>, <?php echo Text::_('JGLOBAL_SORTED_BY'); ?>
                        </caption>
                        <thead>
                        <tr>
                            <td style="width:1%" class="text-center">
		                        <?php echo HTMLHelper::_('grid.checkall'); ?>
                            </td>
                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
		                        <?php echo HTMLHelper::_('searchtools.sort',
		                        'COM_EVENTSCHEDULE_TABLE_LOCATOR_TABLEHEAD_STARTTIME', $listDirn, $listOrder);
                                ?>
                            </th>
                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
		                        <?php echo HTMLHelper::_('searchtools.sort',
		                        'COM_EVENTSCHEDULE_TABLE_LOCATOR_TABLEHEAD_ENDTIME', $listDirn, $listOrder);
                                ?>
                            </th>
                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
		                        <?php echo HTMLHelper::_('searchtools.sort',
		                        'COM_EVENTSCHEDULE_TABLE_LOCATOR_TABLEHEAD_TRACK', $listDirn, $listOrder);
                                ?>
                            </th>
                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
		                        <?php echo HTMLHelper::_('searchtools.sort',
		                        'COM_EVENTSCHEDULE_TABLE_LOCATOR_TABLEHEAD_DAYSCHEDULE', $listDirn, $listOrder);
                                ?>
                            </th>
                        </tr>
                        </thead>
                        <tbody>
						<?php
						$n = count($this->items);
						foreach ($this->items as $i => $item) :
							?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
									<?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>

                                <td class="text-center d-none d-md-table-cell">
	                                <?php echo $item->starttime; ?>
                                </td>
                                 <td class="text-center d-none d-md-table-cell">
	                                <?php echo $item->endtime; ?>
                                </td>
                                 <td class="text-center d-none d-md-table-cell">
	                                <?php echo $item->track; ?>
                                </td>
                                 <td class="text-center d-none d-md-table-cell">
	                                <?php echo $item->daySchedule; ?>
                                </td>
 
                            </tr>
						<?php endforeach; ?>
                        </tbody>
                    </table>

					<?php echo $this->pagination->getListFooter(); ?>

					<?php /*echo HTMLHelper::_(
						'bootstrap.renderModal',
						'collapseModal',
						[
							'title'  => Text::_('COM_EVENTSCHEDULE_BATCH_OPTIONS_SCHEDULEMAKER'),
							'footer' => $this->loadTemplate('batch_footer'),
						],
						$this->loadTemplate('batch_body')
					);*/ ?>

				<?php endif; ?>
                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
				<?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
