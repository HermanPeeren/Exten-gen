<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * What a generation run did, and the package it made: step 5.5.
 *
 * The log lines are built by the generators and the model, some with `<b>` in
 * them, and every value from the model inside them is escaped where the line
 * is made. So they are printed as they are, as the view did before it had a
 * layout.
 *
 * @var \Yepr\Component\Extengen\Administrator\View\Generate\HtmlView $this
 */
?>
<div class="p-3" id="generate-result">
	<h2><?php echo Text::_('COM_EXTENGEN_GENERATE_LOG'); ?></h2>

	<?php if ($this->generator !== null) : ?>
		<p class="text-muted"><?php echo Text::sprintf('COM_EXTENGEN_GENERATE_WITH', $this->escape($this->generator->label())); ?></p>
	<?php endif; ?>

	<?php if ($this->error !== '') : ?>
		<div class="alert alert-danger" role="alert"><?php echo $this->escape($this->error); ?></div>
	<?php endif; ?>

	<?php if ($this->downloadUrl !== '') : ?>
		<p class="d-flex gap-2">
			<a class="btn btn-success" id="generate-download" href="<?php echo $this->escape($this->downloadUrl); ?>">
				<span class="icon-download" aria-hidden="true"></span>
				<?php echo Text::_('COM_EXTENGEN_GENERATE_DOWNLOAD'); ?>
			</a>
			<?php if ($this->installUrl !== '') : ?>
				<a class="btn btn-warning" id="generate-install" href="<?php echo $this->escape($this->installUrl); ?>" target="_top"
				   onclick="return confirm(<?php echo $this->escape(json_encode(Text::_('COM_EXTENGEN_GENERATE_INSTALL_CONFIRM'))); ?>);">
					<span class="icon-upload" aria-hidden="true"></span>
					<?php echo Text::_('COM_EXTENGEN_GENERATE_INSTALL'); ?>
				</a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<p><?php echo implode("<br />\n", $this->log ?? []); ?></p>
</div>
