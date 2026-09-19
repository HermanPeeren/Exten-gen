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
 * The back-end controllers, models, views and layouts.
 *
 * Three hundred lines, and the shape of them was: loop over the back-end
 * section's page references, derive a `$pageType` of 'Index' or 'Details' with
 * a ternary, then loop over the three letters of MVC building a template name
 * and an output path by string concatenation, with an `if ($MVCtype != 'View')`
 * and an `if ($MVCtype == 'View')` inside to handle the one that is spelled
 * differently. Which template produced which file was the product of two loops
 * and four branches.
 *
 * It is nine rules now - index and details for each of controller, model, view
 * and layout, and one for the DisplayController - and each of them names its
 * template and its output path outright. The ternary is gone because the
 * condition is on the rule.
 *
 * @since  0.8.0
 */
class AdminMVC extends RuleDrivenGenerator
{
	/**
	 * The rules that produce the back-end MVC.
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	public function rulePrefix(): string
	{
		return 'admin.mvc.';
	}
}
