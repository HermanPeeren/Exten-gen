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

/** @var \Yepr\Component\Extengen\Administrator\View\Info\HtmlView $this */

$repositories = [
    'Exten-gen' => 'https://github.com/HermanPeeren/Exten-gen',
    'Meta-gen'  => 'https://github.com/HermanPeeren/Meta-gen',
    'Gen-gen'   => 'https://github.com/HermanPeeren/Gen-gen',
];
?>
<div class="p-3" id="extengen-info">
	<p class="text-muted"><?php echo Text::_('COM_EXTENGEN_INFO_DATE'); ?></p>

	<h2><?php echo Text::_('COM_EXTENGEN_INFO_EXTENGEN_HEADING'); ?></h2>
	<p><?php echo Text::_('COM_EXTENGEN_INFO_EXTENGEN'); ?></p>
	<ul>
		<li><?php echo Text::_('COM_EXTENGEN_INFO_EXTENGEN_LANGUAGE'); ?></li>
		<li><?php echo Text::_('COM_EXTENGEN_INFO_EXTENGEN_TARGETS'); ?></li>
		<li><?php echo Text::_('COM_EXTENGEN_INFO_EXTENGEN_GENERATORS'); ?></li>
		<li><?php echo Text::_('COM_EXTENGEN_INFO_EXTENGEN_OUTPUT'); ?></li>
		<li><?php echo Text::_('COM_EXTENGEN_INFO_EXTENGEN_SITE'); ?></li>
	</ul>

	<h2><?php echo Text::_('COM_EXTENGEN_INFO_METAGEN_HEADING'); ?></h2>
	<p><?php echo Text::_('COM_EXTENGEN_INFO_METAGEN'); ?></p>

	<h2><?php echo Text::_('COM_EXTENGEN_INFO_GENGEN_HEADING'); ?></h2>
	<p><?php echo Text::_('COM_EXTENGEN_INFO_GENGEN'); ?></p>

	<h2><?php echo Text::_('COM_EXTENGEN_INFO_SHARED_HEADING'); ?></h2>
	<p><?php echo Text::_('COM_EXTENGEN_INFO_SHARED'); ?></p>

	<h2><?php echo Text::_('COM_EXTENGEN_INFO_SOURCE_HEADING'); ?></h2>
	<ul>
		<?php foreach ($repositories as $name => $url) : ?>
			<li><a href="<?php echo $url; ?>" target="_blank" rel="noopener"><?php echo $name; ?></a></li>
		<?php endforeach; ?>
	</ul>
</div>
