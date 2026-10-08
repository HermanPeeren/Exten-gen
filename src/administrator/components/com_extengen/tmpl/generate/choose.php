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
use Joomla\CMS\Router\Route;
use Yepr\Component\Extengen\Administrator\Generator\Target\Targets;

/**
 * Choose a generator: step 5.5.
 *
 * A GET form back to this view, so that what is chosen ends up in the address
 * - the same `&generator=` a spec or a bookmark can carry - and the result is a
 * page of its own in the modal rather than something a script assembles.
 *
 * @var \Yepr\Component\Extengen\Administrator\View\Generate\HtmlView $this
 */
$app      = \Joomla\CMS\Factory::getApplication();
$isSite   = $app->isClient('site');
$checked  = Targets::DEFAULT;
$ids      = array_map(static fn ($entry): string => $entry->id, $this->generators);

if (!\in_array($checked, $ids, true)) {
	$checked = $ids[0] ?? '';
}
?>
<div class="p-3" id="generate-choose">
	<h2><?php echo Text::_('COM_EXTENGEN_GENERATE_CHOOSE'); ?></h2>

	<?php if ($this->error !== '') : ?>
		<div class="alert alert-warning" role="alert"><?php echo $this->escape($this->error); ?></div>
	<?php else : ?>
		<form action="<?php echo Route::_('index.php'); ?>" method="get" id="generate-choose-form">
			<input type="hidden" name="option" value="com_extengen">
			<input type="hidden" name="view" value="generate">
			<?php if (!$isSite) : ?>
				<input type="hidden" name="tmpl" value="component">
			<?php endif; ?>
			<input type="hidden" name="project_id" value="<?php echo (int) $this->projectId; ?>">

			<fieldset class="mb-3">
				<legend class="visually-hidden"><?php echo Text::_('COM_EXTENGEN_GENERATE_CHOOSE'); ?></legend>
				<?php foreach ($this->generators as $entry) : ?>
					<?php $id = 'generator-' . preg_replace('/[^a-z0-9]+/i', '-', $entry->id); ?>
					<div class="form-check">
						<input class="form-check-input" type="radio" name="generator" id="<?php echo $id; ?>"
							value="<?php echo $this->escape($entry->id); ?>"<?php echo $entry->id === $checked ? ' checked' : ''; ?>>
						<label class="form-check-label" for="<?php echo $id; ?>">
							<?php echo $this->escape($entry->label()); ?>
							<?php if ($entry->builtIn) : ?>
								<span class="badge bg-secondary"><?php echo Text::_('COM_EXTENGEN_GENERATOR_BUILT_IN'); ?></span>
							<?php endif; ?>
						</label>
					</div>
				<?php endforeach; ?>
			</fieldset>

			<button type="submit" class="btn btn-primary" id="generate-run">
				<?php echo Text::_('COM_EXTENGEN_BUTTON_GENERATE'); ?>
			</button>
		</form>
	<?php endif; ?>
</div>
