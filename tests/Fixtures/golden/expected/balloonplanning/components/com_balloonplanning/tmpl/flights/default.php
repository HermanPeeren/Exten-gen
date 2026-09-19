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

// Import CSS
$wa =  $this->document->getWebAssetManager();
$wa->useStyle('com_balloonplanning.admin')
->useScript('com_balloonplanning.admin');

$user      = Factory::getApplication()->getIdentity();
$userId    = $user->get('id');
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
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="flightsList">
                    <thead>
                    <tr>
                        <th class="w-1 text-center">
                            <input type="checkbox" autocomplete="off" class="form-check-input" name="checkall-toggle" value=""
                                   title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                        </th>

                                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort',
                            'COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_BALLOON', $listDirn, $listOrder);
                            ?>
                        </th>
                                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort',
                            'COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_DATE', $listDirn, $listOrder);
                            ?>
                        </th>
                                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort',
                            'COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_MORNING_EVENING', $listDirn, $listOrder);
                            ?>
                        </th>
                                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort',
                            'COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_FLIGHT_NUMBER', $listDirn, $listOrder);
                            ?>
                        </th>
                                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort',
                            'COM_BALLOONPLANNING_TABLE_PLANNEDFLIGHT_TABLEHEAD_DEPARTUREPLACE', $listDirn, $listOrder);
                            ?>
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
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        </td>


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
