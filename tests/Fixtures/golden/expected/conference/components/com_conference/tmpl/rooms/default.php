<?php
/**
 * @package    MyConference
 * @subpackage Conference
 * @version    1.0.0
 *
 * @copyright  Herman Peeren - Yepr - 2023
 * @license    GPL 3.0
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
$wa->useStyle('com_conference.admin')
->useScript('com_conference.admin');

$user      = Factory::getApplication()->getIdentity();
$userId    = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$canOrder  = $user->authorise('core.edit.state', 'com_conference');



if (!empty($saveOrder))
{
$saveOrderingUrl = 'index.php?option=com_conference&task=rooms.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_conference&view=rooms'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="roomsList">
                    <thead>
                    <tr>
                        <th class="w-1 text-center">
                            <input type="checkbox" autocomplete="off" class="form-check-input" name="checkall-toggle" value=""
                                   title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                        </th>

                                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort',
                            'COM_CONFERENCE_TABLE_ROOM_TABLEHEAD_ROOM_NAME', $listDirn, $listOrder);
                            ?>
                        </th>
                                            <th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
                            <?php echo HTMLHelper::_('searchtools.sort',
                            'COM_CONFERENCE_TABLE_ROOM_TABLEHEAD_POSITION', $listDirn, $listOrder);
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
                    $canCreate  = $user->authorise('core.create', 'com_conference');
                    $canEdit    = $user->authorise('core.edit', 'com_conference');
                    $canCheckin = $user->authorise('core.manage', 'com_conference');
                    $canChange  = $user->authorise('core.edit.state', 'com_conference');
                    ?>
                    <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                        <td class="text-center">
                            <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                        </td>


                                                                                    <td>
                                    <a class="hasTooltip" href="<?php
                                    echo Route::_('index.php?option=com_conference&task=room.edit&id=' . (int) $item->id); ?>"
                                       title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape(addslashes($item->room_name)); ?>">
                                        <?php //echo $editIcon; ?><?php echo $this->escape($item->room_name); ?></a>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->position; ?>
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
