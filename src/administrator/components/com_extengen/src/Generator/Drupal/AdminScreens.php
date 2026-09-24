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
 * The screens: routes in YAML, a controller per listing, a form per detail page.
 *
 * ER1's pages land here about as differently again as they did on WordPress. A
 * Drupal screen is not a class Drupal finds by name, nor a callback registered
 * against a hook: it is a *route*, declared in YAML, naming a controller method
 * or a form class and a permission. Nothing is discovered - if a route is not in
 * the file, the page is a 404 whatever classes exist beside it.
 *
 * So this generator writes two YAML files and then the PHP they point at, and
 * the order matters only to whoever reads the output: nothing accumulates.
 *
 * *A controller returns a render array rather than markup*, which is the thing
 * a generator most wants to get wrong. Drupal decides how a table is drawn,
 * caches the result and invalidates it by tag; a generator that emitted
 * `<table>` would produce a page that works and is uncacheable, which is worse
 * than one that fails.
 *
 * @since  1.4.0
 */
final class AdminScreens extends DrupalGenerator
{
    /**
     * @return  string[]  A log of what was produced.
     *
     * @since   1.4.0
     */
    protected function generateFiles(): array
    {
        $screens = $this->screens('indexpage');
        $forms   = $this->screens('detailspage');

        if ($screens === [] && $forms === []) {
            return [];
        }

        $variables = $this->header() + ['screens' => $screens, 'forms' => $forms];

        $log = [];

        foreach (
            [
                'routing.yml.twig'    => $this->machineName() . '.routing.yml',
                'links.menu.yml.twig' => $this->machineName() . '.links.menu.yml',
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

        foreach ($screens as $screen) {
            $log = array_merge($log, $this->generateFileWithTemplate(
                'module/',
                'controller.php.twig',
                $this->moduleRoot() . 'src/Controller/',
                $screen['class'] . 'Controller.php',
                $this->header() + ['screen' => $screen]
            ));
        }

        foreach ($forms as $form) {
            $log = array_merge($log, $this->generateFileWithTemplate(
                'module/',
                'form.php.twig',
                $this->moduleRoot() . 'src/Form/',
                $form['class'] . 'Form.php',
                $this->header() + ['form' => $form]
            ));
        }

        return $log;
    }
}
