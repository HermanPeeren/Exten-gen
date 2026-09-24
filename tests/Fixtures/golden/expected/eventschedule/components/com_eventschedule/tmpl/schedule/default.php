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

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

// <extengen id="site.index.layout">
// </extengen>

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// No stylesheet and no script: this component generates neither, and a
// front-end layout used to ask the asset manager for `com_x.admin` - an
// administrator asset, on the site, that no generated component declares.
// Joomla throws for an asset it does not know, so every generated front
// end was a 500 on its first page.

$user      = Factory::getApplication()->getIdentity();
$userId    = $user->id;
$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$canOrder  = $user->authorise('core.edit.state', 'com_eventschedule');



if (!empty($saveOrder))
{
$saveOrderingUrl = 'index.php?option=com_eventschedule&task=schedule.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_eventschedule&view=schedule'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php // No search tools. That layout reads a filter form and a set of
                     // active filters off the view, and a front-end list model builds
                     // neither - so rendering it was "Call to a member function
                     // getGroup() on null" on the first page anybody visited. ?>
                <div class="clearfix"></div>
                <table class="table table-striped" id="scheduleList">
                    <thead>
                    <tr>

                                            <th scope="col">
                            <?php echo Text::_('COM_EVENTSCHEDULE_TABLE_PRESENTATION_TABLEHEAD_PRESENTATION_NAME'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_EVENTSCHEDULE_TABLE_PRESENTATION_TABLEHEAD_SHORT_DESCRIPTION'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_EVENTSCHEDULE_TABLE_PRESENTATION_TABLEHEAD_LONG_DESCRIPTION'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_EVENTSCHEDULE_TABLE_PRESENTATION_TABLEHEAD_DURATION'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_EVENTSCHEDULE_TABLE_PRESENTATION_TABLEHEAD_LOCATORS'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_EVENTSCHEDULE_TABLE_PRESENTATION_TABLEHEAD_PRESENTATION_TYPE'); ?>
                        </th>
                    
                                        </tr>
                    </thead>
                    <tfoot>
                    <tr>
                        <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                            <?php echo $this->pagination->getListFooter(); ?>
                        </td>
                    </tr>
                    </tfoot>
                    <tbody <?php if (!empty($saveOrder)) :?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php endif; ?>>
                    <?php foreach ($this->items as $i => $item) :
                    $ordering   = ($listOrder == 'a.ordering');
                    $canCreate  = $user->authorise('core.create', 'com_eventschedule');
                    $canEdit    = $user->authorise('core.edit', 'com_eventschedule');
                    $canCheckin = $user->authorise('core.manage', 'com_eventschedule');
                    $canChange  = $user->authorise('core.edit.state', 'com_eventschedule');
                    ?>
                    <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>

                                                                                    <td>
                                    <a class="hasTooltip" href="<?php
                                    echo Route::_('index.php?option=com_eventschedule&view=schedule&id=' . (int) $item->id); ?>"
                                       title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape(addslashes($item->presentation_name)); ?>">
                                        <?php //echo $editIcon; ?><?php echo $this->escape($item->presentation_name); ?></a>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->short_description; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->long_description; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->duration; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->locators; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->presentation_type; ?>
                                </td>
                                                                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
