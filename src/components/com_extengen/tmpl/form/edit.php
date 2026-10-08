<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * One project, edited on the frontend: step 5.7.
 *
 * The administrator's layout, included rather than copied, so that the tabs
 * and the reference dropdowns are the same code in both places. What the
 * frontend adds is the buttons, which the administrator has in its toolbar.
 *
 * @var \Yepr\Component\Extengen\Site\View\Form\HtmlView $this
 */
?>
<div class="com-extengen-project" id="extengen-site-project">
	<h1><?php echo $this->escape($this->item->name ?: Text::_('COM_EXTENGEN_SITE_NEW_PROJECT')); ?></h1>

	<?php require JPATH_ADMINISTRATOR . '/components/com_extengen/tmpl/project/edit.php'; ?>

	<div class="mt-3 d-flex gap-2" id="extengen-site-project-buttons">
		<button type="button" class="btn btn-primary" id="extengen-save" onclick="Joomla.submitbutton('project.apply')">
			<?php echo Text::_('JSAVE'); ?>
		</button>
		<button type="button" class="btn btn-secondary" id="extengen-save-close" onclick="Joomla.submitbutton('project.save')">
			<?php echo Text::_('JSAVEANDCLOSE'); ?>
		</button>
		<button type="button" class="btn btn-outline-secondary" id="extengen-cancel" onclick="Joomla.submitbutton('project.cancel')">
			<?php echo Text::_('JCANCEL'); ?>
		</button>
		<?php if ((int) $this->item->id > 0) : ?>
			<a class="btn btn-info ms-auto" id="extengen-generate"
			   href="<?php echo Route::_('index.php?option=com_extengen&view=generate&project_id=' . (int) $this->item->id); ?>">
				<?php echo Text::_('COM_EXTENGEN_BUTTON_GENERATE'); ?>
			</a>
		<?php endif; ?>
	</div>
</div>
