<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Yepr\Component\Extengen\Site\View\Projects\HtmlView $this */
?>
<div class="com-extengen-projects" id="extengen-site-projects">
	<h1><?php echo Text::_('COM_EXTENGEN_SITE_MY_PROJECTS'); ?></h1>

	<p class="lead"><?php echo Text::_('COM_EXTENGEN_SITE_MY_PROJECTS_INTRO'); ?></p>

	<?php if ($this->canCreate) : ?>
		<form action="<?php echo Route::_('index.php?option=com_extengen'); ?>" method="post" class="mb-3">
			<button type="submit" class="btn btn-primary" id="extengen-new-project">
				<span class="icon-plus" aria-hidden="true"></span>
				<?php echo Text::_('COM_EXTENGEN_SITE_NEW_PROJECT'); ?>
			</button>
			<input type="hidden" name="task" value="project.add">
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
	<?php endif; ?>

	<?php if ($this->items === []) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_EXTENGEN_SITE_NO_PROJECTS'); ?></div>
	<?php else : ?>
		<table class="table" id="extengenSiteProjects">
			<caption class="visually-hidden"><?php echo Text::_('COM_EXTENGEN_SITE_MY_PROJECTS'); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php echo Text::_('COM_EXTENGEN_TABLE_TABLEHEAD_NAME'); ?></th>
					<th scope="col"><?php echo Text::_('COM_EXTENGEN_TABLE_TABLEHEAD_METALANGUAGE'); ?></th>
					<th scope="col"><?php echo Text::_('COM_EXTENGEN_TABLE_TABLEHEAD_GENERATION'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($this->items as $item) : ?>
				<tr data-id="<?php echo (int) $item->id; ?>">
					<th scope="row">
						<a href="<?php echo Route::_('index.php?option=com_extengen&task=project.edit&id=' . (int) $item->id); ?>">
							<?php echo $this->escape($item->name); ?>
						</a>
					</th>
					<td>
						<?php echo $this->escape($item->metalanguage_name ?? $item->metalanguage_key); ?>
						<span class="badge bg-secondary"><?php echo $this->escape($item->metalanguage_version); ?></span>
					</td>
					<td>
						<a class="btn btn-sm btn-info extengen-generate"
						   href="<?php echo Route::_('index.php?option=com_extengen&view=generate&project_id=' . (int) $item->id); ?>">
							<?php echo Text::_('COM_EXTENGEN_BUTTON_GENERATE'); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
