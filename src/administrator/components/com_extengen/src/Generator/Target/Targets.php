<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Target;

use Yepr\Gen\Core\Target\TargetRegistry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What this component can generate a project into.
 *
 * `TargetRegistry` has been in the shared library since 0.4 and nothing had
 * ever put two things in one, because there was only ever one target to put
 * there. 4.4 is where that stops being true, and this is the list.
 *
 * Three of them now. The third cost one line here, which is the number that
 * says whether the second one proved anything.
 *
 * The default is Joomla 6, and deliberately by name rather than "the first
 * one": a registry is unordered as far as anybody reading this is concerned,
 * and a default that moved when somebody added a target would be a surprise
 * arriving through a screen nobody touched.
 *
 * @since  1.4.0
 */
final class Targets
{
    /**
     * The target a project is generated into when nobody says otherwise.
     *
     * @since  1.4.0
     */
    public const DEFAULT = 'joomla6';

    /**
     * Every target, ready to run.
     *
     * @param   string   $templateRoot    Directory holding the template sets.
     * @param   ?string  $cacheDirectory  Where Twig may cache compiled templates.
     *
     * @since   1.4.0
     */
    public static function registry(string $templateRoot, ?string $cacheDirectory = null): TargetRegistry
    {
        return new TargetRegistry(
            new Joomla6Target($templateRoot, $cacheDirectory),
            new WordPressTarget($templateRoot, $cacheDirectory),
            new DrupalTarget($templateRoot, $cacheDirectory)
        );
    }
}
