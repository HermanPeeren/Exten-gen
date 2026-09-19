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
 * The files every component has, whatever it models.
 *
 * Five of them come from rules: the access and config XML, the licence, the
 * service provider and the Extension class. What stays here is the language
 * strings, because they are not a file - they are entries the language
 * generator writes out later, and no template renders them.
 *
 * @since  0.8.0
 */
class AdminGeneral extends RuleDrivenGenerator
{
	/**
	 * Joomla's own count messages, which every component needs and no model describes.
	 *
	 * Sixteen `addLanguageString` calls with five arguments each, most of them
	 * empty strings. As a table it is the same sixteen strings and it is
	 * possible to see that they are sixteen of one thing.
	 *
	 * @var    array<string, string>
	 * @since  1.1.0
	 */
	private const ITEM_COUNTS = [
		'N_ITEMS_PUBLISHED'       => '%d items published.',
		'N_ITEMS_PUBLISHED_1'     => '%d item published.',
		'N_ITEMS_UNPUBLISHED'     => '%d items unpublished.',
		'N_ITEMS_UNPUBLISHED_1'   => '%d item unpublished.',
		'N_ITEMS_CHECKED_IN_1'    => '%d item checked in.',
		'N_ITEMS_CHECKED_IN_MORE' => '%d items checked in.',
		'N_ITEMS_FEATURED'        => '%d items featured.',
		'N_ITEMS_FEATURED_1'      => '%d item featured.',
		'N_ITEMS_UNFEATURED'      => '%d items unfeatured.',
		'N_ITEMS_UNFEATURED_1'    => '%d item unfeatured.',
		'N_ITEMS_ARCHIVED'        => '%d items archived.',
		'N_ITEMS_ARCHIVED_1'      => '%d item archived.',
		'N_ITEMS_DELETED'         => '%d items deleted.',
		'N_ITEMS_DELETED_1'       => '%d item deleted.',
		'N_ITEMS_TRASHED'         => '%d items trashed.',
		'N_ITEMS_TRASHED_1'       => '%d item trashed.',
	];

	/**
	 * The rules that produce the component's general files.
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	public function rulePrefix(): string
	{
		return 'admin.general.';
	}

	/**
	 * The language strings that belong to no template.
	 *
	 * Registered after the rules have run, which is where they were registered
	 * before: the language files are written last and hold their strings in the
	 * order they arrived.
	 *
	 * @return  string[]
	 *
	 * @since   1.1.0
	 */
	protected function generateBeyondRules(): array
	{
		$componentName = ucfirst($this->componentName);

		// The component's own name, and the title of its options screen.
		$this->languageStringUtil->addLanguageString($componentName, '', '', '', $componentName);
		$this->languageStringUtil->addLanguageString(
			$componentName,
			'',
			'',
			'CONFIGURATION',
			$this->AST->name . ' options'
		);

		foreach (self::ITEM_COUNTS as $key => $text) {
			$this->languageStringUtil->addLanguageString($componentName, '', '', $key, $text);
		}

		// And the sys.ini entry, which is what the extension manager shows.
		$this->languageStringUtil->addLanguageString(
			$componentName,
			'',
			'',
			'',
			$componentName,
			'Administrator',
			true
		);

		return [];
	}
}
