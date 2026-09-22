<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** @var \Yepr\Component\Extengen\Administrator\View\Metalanguages\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_extengen&view=metalanguages'); ?>"
      method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

	<div class="row">
		<div class="col-md-8">
			<table class="table" id="metalanguageList">
				<caption class="visually-hidden"><?php echo Text::_('COM_EXTENGEN_MANAGER_METALANGUAGES'); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
						<th scope="col"><?php echo Text::_('COM_EXTENGEN_METALANGUAGE_VERSION'); ?></th>
						<th scope="col"><?php echo Text::_('COM_EXTENGEN_METALANGUAGE_ROOT'); ?></th>
						<th scope="col"><?php echo Text::_('COM_EXTENGEN_METALANGUAGE_PROJECTS'); ?></th>
						<th scope="col"></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($this->items as $item) : ?>
					<tr class="metalanguage-row" data-key="<?php echo $this->escape($item->key); ?>">
						<th scope="row">
							<?php echo $this->escape($item->name); ?>
							<?php if ($item->isBuiltIn()) : ?>
								<span class="badge bg-secondary"><?php echo Text::_('COM_EXTENGEN_METALANGUAGE_BUILT_IN'); ?></span>
							<?php endif; ?>
							<div class="small text-muted"><?php echo $this->escape($item->formRoot); ?></div>
						</th>
						<td><?php echo $this->escape($item->version); ?></td>
						<td><?php echo $this->escape($item->root); ?></td>
						<td><?php echo (int) ($this->counts[$item->isBuiltIn() ? '' : $item->binding()] ?? 0); ?></td>
						<td class="text-end">
							<?php if (!$item->isBuiltIn()) : ?>
								<a class="btn btn-sm btn-danger"
								   href="<?php echo Route::_('index.php?option=com_extengen&task=metalanguages.remove&id=' . (int) ($item->id ?? 0) . '&' . Session::getFormToken() . '=1'); ?>">
									<?php echo Text::_('COM_EXTENGEN_METALANGUAGE_FORGET'); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="col-md-4">
			<fieldset class="options-form">
				<legend><?php echo Text::_('COM_EXTENGEN_METALANGUAGE_IMPORT'); ?></legend>

				<p class="small text-muted">
					<?php echo Text::_('COM_EXTENGEN_METALANGUAGE_IMPORT_DESC'); ?>
				</p>

				<div class="mb-3">
					<input class="form-control" type="file" name="package" id="package" accept=".zip">
				</div>

				<button class="btn btn-primary" type="submit"
				        onclick="document.getElementById('task').value='metalanguages.import';">
					<?php echo Text::_('COM_EXTENGEN_METALANGUAGE_IMPORT'); ?>
				</button>
			</fieldset>
		</div>
	</div>

	<input type="hidden" name="task" id="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
