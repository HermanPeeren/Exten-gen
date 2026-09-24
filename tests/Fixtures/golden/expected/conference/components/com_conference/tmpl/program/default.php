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
$canOrder  = $user->authorise('core.edit.state', 'com_conference');



if (!empty($saveOrder))
{
$saveOrderingUrl = 'index.php?option=com_conference&task=program.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_conference&view=program'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php // No search tools. That layout reads a filter form and a set of
                     // active filters off the view, and a front-end list model builds
                     // neither - so rendering it was "Call to a member function
                     // getGroup() on null" on the first page anybody visited. ?>
                <div class="clearfix"></div>
                <table class="table table-striped" id="programList">
                    <thead>
                    <tr>

                                            <th scope="col">
                            <?php echo Text::_('COM_CONFERENCE_TABLE_PROGRAM_TABLEHEAD_TITLE'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_CONFERENCE_TABLE_PROGRAM_TABLEHEAD_TIME'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_CONFERENCE_TABLE_PROGRAM_TABLEHEAD_TALK'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_CONFERENCE_TABLE_PROGRAM_TABLEHEAD_ROOM'); ?>
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

                                                                                    <td>
                                    <a class="hasTooltip" href="<?php
                                    echo Route::_('index.php?option=com_conference&view=session&id=' . (int) $item->id); ?>"
                                       title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape(addslashes($item->title)); ?>">
                                        <?php //echo $editIcon; ?><?php echo $this->escape($item->title); ?></a>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->time; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->talk; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->room; ?>
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
