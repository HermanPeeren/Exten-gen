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

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

// This page's stylesheet, from the page's custom code in the model. Loaded
// before the layout slot, so that a custom layout which returns early has it.
$this->getDocument()->getWebAssetManager()->registerAndUseStyle(
	'com_balloonledger.accounts',
	'com_balloonledger/accounts.css'
);

// <extengen id="site.index.layout">
// </extengen>

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// No other stylesheet and no script. A front-end layout used to ask the
// asset manager for `com_x.admin` - an
// administrator asset, on the site, that no generated component declares.
// Joomla throws for an asset it does not know, so every generated front
// end was a 500 on its first page.

$user      = Factory::getApplication()->getIdentity();
$userId    = $user->id;
$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
$canOrder  = $user->authorise('core.edit.state', 'com_balloonledger');



if (!empty($saveOrder))
{
$saveOrderingUrl = 'index.php?option=com_balloonledger&task=accounts.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
HTMLHelper::_('draggablelist.draggable');
}

?>

<form action="<?php echo Route::_('index.php?option=com_balloonledger&view=accounts'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php // No search tools. That layout reads a filter form and a set of
                     // active filters off the view, and a front-end list model builds
                     // neither - so rendering it was "Call to a member function
                     // getGroup() on null" on the first page anybody visited. ?>
                <div class="clearfix"></div>
                <table class="table table-striped" id="accountsList">
                    <thead>
                    <tr>

                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONLEDGER_TABLE_ACCOUNT_TABLEHEAD_CODE'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONLEDGER_TABLE_ACCOUNT_TABLEHEAD_NAME'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONLEDGER_TABLE_ACCOUNT_TABLEHEAD_NATURE'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONLEDGER_TABLE_ACCOUNT_TABLEHEAD_NORMAL_BALANCE'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONLEDGER_TABLE_ACCOUNT_TABLEHEAD_POSTABLE'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONLEDGER_TABLE_ACCOUNT_TABLEHEAD_RECONCILABLE'); ?>
                        </th>
                                            <th scope="col">
                            <?php echo Text::_('COM_BALLOONLEDGER_TABLE_ACCOUNT_TABLEHEAD_ACTIVE'); ?>
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
                    $canCreate  = $user->authorise('core.create', 'com_balloonledger');
                    $canEdit    = $user->authorise('core.edit', 'com_balloonledger');
                    $canCheckin = $user->authorise('core.manage', 'com_balloonledger');
                    $canChange  = $user->authorise('core.edit.state', 'com_balloonledger');
                    ?>
                    <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>

                                                                                    <td>
                                    <a class="hasTooltip" href="<?php
                                    echo Route::_('index.php?option=com_balloonledger&view=account&id=' . (int) $item->id); ?>"
                                       title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape(addslashes($item->code)); ?>">
                                        <?php //echo $editIcon; ?><?php echo $this->escape($item->code); ?></a>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->name; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->nature; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->normal_balance; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->postable; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->reconcilable; ?>
                                </td>
                                                                                                                <td class="text-center d-none d-md-table-cell">
                                    <?php echo $item->active; ?>
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
