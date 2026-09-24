<?php

/**
 * @package     Extengen

 * @subpackage  Extengen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Rule;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\FormRule;

/**
 * Form Rule class for the Joomla Platform.
 */
class LetterRule extends FormRule
{
	/**
	 * The regular expression to use in testing a form field value.
	 *
	 * @var    string
	 */
	protected $regex = '^([a-z]+)$';

	/**
	 * The regular expression modifiers to use when testing a form field value.
	 *
	 * `D` because PCRE's `$` also matches immediately before a final newline,
	 * and JavaScript's does not - so without it this rule accepted a name the
	 * browser had already refused, and this is the side that decides what gets
	 * stored. A component name with a newline in it goes into a namespace and
	 * every generated class name.
	 *
	 * @var    string
	 */
	protected $modifiers = 'iD';
}
