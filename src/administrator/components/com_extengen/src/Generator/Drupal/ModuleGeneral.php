<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Drupal;

/**
 * What makes a directory a Drupal module, and who may use it.
 *
 * `<machine_name>.info.yml` is the whole of what Drupal reads to decide the
 * module exists, and it has to be named for the directory it is in: a module
 * whose directory and info file disagree does not appear in the list at all,
 * with no error to say why. That is the same class of silent absence as a
 * Joomla manifest that does not list a folder, and as a WordPress plugin whose
 * header comment is malformed - three CMSs, three conventions, and all three
 * fail by the thing simply not being there.
 *
 * The permissions file is here rather than with the screens because a
 * permission outlives any particular screen: routes come and go, and the answer
 * to "who may administer this module" is a property of the module.
 *
 * @since  1.4.0
 */
final class ModuleGeneral extends DrupalGenerator
{
    /**
     * @return  string[]  A log of what was produced.
     *
     * @since   1.4.0
     */
    protected function generateFiles(): array
    {
        $variables = $this->header() + [
            'entities' => $this->entities(),
            'screens'  => $this->screens('indexpage'),
        ];

        $log = [];

        foreach (
            [
                'info.yml.twig'        => $this->machineName() . '.info.yml',
                'permissions.yml.twig' => $this->machineName() . '.permissions.yml',
                'README.md.twig'       => 'README.md',
            ] as $template => $name
        ) {
            $log = array_merge($log, $this->generateFileWithTemplate(
                'module/',
                $template,
                $this->moduleRoot(),
                $name,
                $variables
            ));
        }

        return $log;
    }
}
