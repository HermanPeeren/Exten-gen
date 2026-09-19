<?php
/**
 * @package    BalloonPlanning
 * @subpackage BalloonPlanning
 * @version    6.0.0 alpha
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL3
 */

defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

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
$canOrder  = $user->authorise('core.edit.state', 'com_balloonplanning');



if (!empty($saveOrder))
{
$saveOrderingUrl = 'index.php?option=com_balloonplanning&task=flights.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_balloonplanning&view=flights'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php // No search tools. That layout reads a filter form and a set of
                     // active filters off the view, and a front-end list model builds
                     // neither - so rendering it was "Call to a member function
                     // getGroup() on null" on the first page anybody visited. ?>
                <div class="clearfix"></div>
                <table class="table table-striped" id="flightsList">
                    <thead>
                    <tr>

                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_BALLOON'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_DATE'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_MORNING_EVENING'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_FLIGHT_NUMBER'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_DEPARTUREPLACE'); ?>
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
                    $canCreate  = $user->authorise('core.create', 'com_balloonplanning');
                    $canEdit    = $user->authorise('core.edit', 'com_balloonplanning');
                    $canCheckin = $user->authorise('core.manage', 'com_balloonplanning');
                    $canChange  = $user->authorise('core.edit.state', 'com_balloonplanning');
                    ?>
                    <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>

                                                                                    <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->balloon; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->date; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->morning_evening; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->flight_number; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->departureplace; ?>
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
