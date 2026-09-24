<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\WordPress;

/**
 * The three files that make a directory a WordPress plugin.
 *
 * The main file, whose header comment is the whole of what WordPress reads to
 * decide the plugin exists; `uninstall.php`, which WordPress includes by name
 * when somebody deletes it; and `readme.txt`, in the format the plugin
 * directory parses.
 *
 * All three are conventions rather than APIs - there is no manifest to validate
 * against and no error if a field is missing, the plugin simply shows up with a
 * blank column. Which is the same class of problem as a Joomla manifest that
 * does not list a folder, arrived at from the opposite direction.
 *
 * @since  1.4.0
 */
final class PluginGeneral extends WordPressGenerator
{
    /**
     * @return  string[]  A log of what was produced.
     *
     * @since   1.4.0
     */
    protected function generateFiles(): array
    {
        $variables = $this->header() + ['entities' => $this->entities()];

        $log = [];

        foreach (
            [
                'plugin.php.twig'   => $this->slug() . '.php',
                'uninstall.php.twig' => 'uninstall.php',
                'readme.txt.twig'    => 'readme.txt',
            ] as $template => $name
        ) {
            $log = array_merge($log, $this->generateFileWithTemplate(
                'plugin/',
                $template,
                $this->pluginRoot(),
                $name,
                $variables
            ));
        }

        return $log;
    }
}
