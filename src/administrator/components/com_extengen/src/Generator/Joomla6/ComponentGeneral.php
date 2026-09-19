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
 * The component's manifest: what Joomla reads to install it.
 *
 * Nothing is left here. This class used to build a `$templateVariables` array
 * out of thirteen manifest properties, join the back-end sections against the
 * page list to find the admin menu items, and pick the default view - sixty
 * lines whose only job was to say "the manifest comes from the manifest". The
 * thirteen are paths in the rule file now and the two joins are named
 * derivations.
 *
 * @since  0.8.0
 */
class ComponentGeneral extends RuleDrivenGenerator
{
	/**
	 * The rules that produce the manifest.
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	public function rulePrefix(): string
	{
		return 'component.';
	}
}
