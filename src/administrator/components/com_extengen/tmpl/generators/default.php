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

/** @var \Yepr\Component\Extengen\Administrator\View\Generators\HtmlView $this */
?>
<form action="<?php echo Route::_('index.php?option=com_extengen&view=generators'); ?>"
      method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

	<div class="row">
		<div class="col-md-8">
			<table class="table" id="generatorList">
				<caption class="visually-hidden"><?php echo Text::_('COM_EXTENGEN_MANAGER_GENERATORS'); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
						<th scope="col"><?php echo Text::_('COM_EXTENGEN_GENERATOR_TARGET'); ?></th>
						<th scope="col"><?php echo Text::_('COM_EXTENGEN_GENERATOR_METALANGUAGE'); ?></th>
						<th scope="col"><?php echo Text::_('COM_EXTENGEN_GENERATOR_RULES'); ?></th>
						<th scope="col"></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($this->items as $item) : ?>
					<tr class="generator-row" data-id="<?php echo $this->escape($item->id); ?>">
						<th scope="row">
							<?php echo $this->escape($item->name); ?>
							<?php if ($item->builtIn) : ?>
								<span class="badge bg-secondary"><?php echo Text::_('COM_EXTENGEN_GENERATOR_BUILT_IN'); ?></span>
							<?php else : ?>
								<div class="small text-muted">
									<?php echo Text::sprintf('COM_EXTENGEN_GENERATOR_IMPORTED_ON', $this->escape($item->imported)); ?>
								</div>
							<?php endif; ?>
						</th>
						<td><?php echo $this->escape($item->targetLabel); ?></td>
						<td>
							<?php echo $this->escape($item->metalanguageKey); ?>
							<?php if ($item->metalanguageVersion !== '') : ?>
								<span class="badge bg-secondary"><?php echo $this->escape($item->metalanguageVersion); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $item->builtIn ? Text::_('COM_EXTENGEN_GENERATOR_RULES_BUILT_IN') : (int) $item->ruleCount; ?></td>
						<td class="text-end">
							<?php if (!$item->builtIn) : ?>
								<a class="btn btn-sm btn-danger"
								   href="<?php echo Route::_('index.php?option=com_extengen&task=generators.remove&id=' . $item->rowId . '&' . Session::getFormToken() . '=1'); ?>">
									<?php echo Text::_('COM_EXTENGEN_GENERATOR_FORGET'); ?>
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
				<legend><?php echo Text::_('COM_EXTENGEN_GENERATOR_IMPORT'); ?></legend>

				<p class="small text-muted">
					<?php echo Text::_('COM_EXTENGEN_GENERATOR_IMPORT_DESC'); ?>
				</p>

				<div class="mb-3">
					<input class="form-control" type="file" name="package" id="package" accept=".zip">
				</div>

				<button class="btn btn-primary" type="submit"
				        onclick="document.getElementById('task').value='generators.import';">
					<?php echo Text::_('COM_EXTENGEN_GENERATOR_IMPORT'); ?>
				</button>
			</fieldset>
		</div>
	</div>

	<input type="hidden" name="task" id="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
