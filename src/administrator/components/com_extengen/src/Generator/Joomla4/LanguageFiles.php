<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Generator\Joomla4;

use Yepr\Component\Extengen\Administrator\Generator\Generator;

/**
 * Writes the language files.
 *
 * It runs last, and that is not a preference. Every other generator adds
 * language strings while its templates render - that is what the
 * `addLanguageString` Twig function does - so the set of strings is only
 * complete once they have all run. This is the case the target's ordering rule
 * exists for.
 *
 * Lifted out of `GenerateModel`, which used to do this itself after calling the
 * generators. That made it the one piece of generated output produced outside a
 * generator, which meant it could not be pinned, moved or reasoned about with
 * the rest - and leaving it there would have left `.ini` files outside the
 * pipeline that the rest of the package goes through.
 *
 * @since  0.9.0
 */
final class LanguageFiles extends Generator
{
    /**
     * Add one file per language, per section.
     *
     * @return string[]  A log of what was produced.
     *
     * @since  0.9.0
     */
    protected function generateFiles(): array
    {
        $log = ['&nbsp;', '<b>=== LANGUAGE FILES ===</b>'];

        $component = strtolower($this->componentName);

        foreach ($this->languageStringUtil->getLangTree() as $sectionName => $section) {
            $path = match ($sectionName) {
                'frontend' => 'components/com_' . $component . '/language/',
                default    => 'administrator/components/com_' . $component . '/language/',
            };

            // A sys file is what Joomla reads before the extension is enabled:
            // the name in the extension manager, and the install messages.
            $suffix = $sectionName === 'sys' ? '.sys' : '';

            foreach ($section->languages as $language) {
                $folder = $language->language_code . '-' . $language->country_code;
                $lines  = [];

                foreach ($language->key_value_pairs as $pair) {
                    $lines[] = $pair->language_string . '="' . $pair->locale_string . '"';
                }

                // Alphabetical, so that adding a string somewhere in the middle
                // of a run does not reshuffle the whole file in the diff.
                sort($lines);

                // todo: add a heading with project, copyright, license and version

                $file = $path . $folder . '/com_' . $component . $suffix . '.ini';

                $this->addFile($file, implode("\n", $lines));

                $log[] = $file . ' generated';
            }
        }

        return $log;
    }
}
