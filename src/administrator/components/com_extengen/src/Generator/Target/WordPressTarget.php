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

use Yepr\Component\Extengen\Administrator\Generator\LanguageStringUtil;
use Yepr\Component\Extengen\Administrator\Generator\Model\ProjectValidator;
use Yepr\Component\Extengen\Administrator\Generator\WordPress\AdminScreens;
use Yepr\Component\Extengen\Administrator\Generator\WordPress\PluginGeneral;
use Yepr\Component\Extengen\Administrator\Generator\WordPress\Schema;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Gen\Core\Target\TargetInterface;
use Yepr\Gen\Core\Template\TwigRenderer;

/**
 * A WordPress plugin: step 4.4.
 *
 * The second target, and the reason the plan wanted one. 0.4 introduced
 * `TargetInterface` and proved it with *a trivial fake target* - which proves
 * the wiring and nothing about whether the seam is in the right place. A real
 * second CMS is the proof, and WordPress is the sharper of the two the plan
 * offered: Drupal is another PHP framework with entities, a container and
 * annotated classes, close enough to Joomla that an abstraction could be wrong
 * and still fit. WordPress has no MVC, no XML forms, no namespaces by
 * convention, no schema installer and no manifest - it has a header comment and
 * a list of hooks.
 *
 * **What survived.** The pipeline, untouched. The model, untouched: ER1 says
 * entities, fields and pages, and it turns out ER1 never mentioned a CMS. The
 * `Generator` base class, untouched - it gives a renderer and a file
 * collection, which is the right amount. `ProjectValidator` is shared, because
 * what makes a model unusable is the same either way.
 *
 * **What did not, and should not have.** Everything about what a file *is*.
 * The Joomla target writes `sql/install.mysql.utf8.sql`, a file Joomla runs
 * once and records in `#__schemas`; this one writes a `dbDelta()` call in an
 * activation hook, which WordPress re-runs on every activation and diffs
 * against the database. Those are not two dialects of one idea, they are two
 * ideas - and the abstraction is in the right place precisely because saying
 * so cost one class rather than a change to the pipeline.
 *
 * *It shares `LanguageStringUtil` with nothing.* The base class takes one
 * because the Joomla generators collect `COM_X_*` constants through it while
 * their templates render; a WordPress plugin writes `__('Text', 'domain')`
 * inline, so one is constructed and never asked for anything. That is a seam
 * that could be narrower, and it is honest to say so rather than to pretend the
 * first two targets found no friction at all.
 *
 * @since  1.4.0
 */
final class WordPressTarget implements TargetInterface
{
    /**
     * The generators, in the order they run.
     *
     * Order matters less here than for Joomla - nothing accumulates across
     * them - so this is the order somebody reads a plugin in: what it is, what
     * it stores, what it shows.
     *
     * @var class-string<GeneratorInterface>[]
     *
     * @since  1.4.0
     */
    public const GENERATORS = [
        PluginGeneral::class,
        Schema::class,
        AdminScreens::class,
    ];

    /**
     * @param  string   $templateRoot    Directory holding generator_templates/WordPress.
     * @param  ?string  $cacheDirectory  Where Twig may cache compiled templates.
     *
     * @since  1.4.0
     */
    public function __construct(
        private readonly string $templateRoot,
        private readonly ?string $cacheDirectory = null
    ) {
    }

    /**
     * The stable identifier.
     *
     * @since  1.4.0
     */
    public function id(): string
    {
        return 'wordpress';
    }

    /**
     * How this target is named to somebody choosing one.
     *
     * @since  1.4.0
     */
    public function label(): string
    {
        return 'WordPress plugin';
    }

    /**
     * What a model must satisfy before generating a plugin from it.
     *
     * The same validator the Joomla target uses, and that is a finding rather
     * than a shortcut: everything it refuses - no component name, no entities,
     * no pages - makes a model unusable whatever it is generated into. A rule
     * that had turned out to be about Joomla would have been in the wrong class
     * since 1.0, and this is the first thing that could have noticed.
     *
     * @since  1.4.0
     */
    public function validator(): ValidatorInterface
    {
        return new ProjectValidator();
    }

    /**
     * The generators, wired to one renderer.
     *
     * @return GeneratorInterface[]
     *
     * @since  1.4.0
     */
    public function generators(): array
    {
        $renderer = TwigRenderer::forDirectories($this->templateSetRoot(), $this->cacheDirectory);

        // Constructed because the base class asks for one, and never used: a
        // WordPress plugin's strings are written into the file it uses them in.
        // See the class comment.
        $languageStringUtil = new LanguageStringUtil();

        $renderer->addExtension($languageStringUtil);

        return array_map(
            static fn (string $class): GeneratorInterface
                => new $class($renderer, $languageStringUtil),
            self::GENERATORS
        );
    }

    /**
     * The directory the template set is rooted at.
     *
     * @since  1.4.0
     */
    public function templateSetRoot(): string
    {
        return rtrim($this->templateRoot, '/\\') . '/WordPress';
    }
}
