<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Generator\Joomla6;

use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;

/**
 * The front-end controllers, models, views, layouts and menu item types.
 *
 * A near-copy of the back-end generator, and it opened with the comment "same
 * as AdminMVC, can we combine that?" - which nobody could answer, because the
 * two were three hundred lines each and the differences between them were four
 * lines buried in the middle. Writing both as rules answers it: the topology is
 * the same eleven shapes, and what genuinely differs is three derivations, each
 * of which now has a name and a docblock saying how it differs and why.
 *
 * @since  0.8.0
 */
class SiteMVC extends RuleDrivenGenerator
{
	/**
	 * The rules that produce the front-end MVC.
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	public function rulePrefix(): string
	{
		return 'site.mvc.';
	}
}
