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

use Yepr\Component\Extengen\Administrator\Generator\Drupal\AdminScreens;
use Yepr\Component\Extengen\Administrator\Generator\Drupal\ModuleGeneral;
use Yepr\Component\Extengen\Administrator\Generator\Drupal\Schema;
use Yepr\Component\Extengen\Administrator\Generator\LanguageStringUtil;
use Yepr\Component\Extengen\Administrator\Generator\Model\ProjectValidator;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Gen\Core\Target\TargetInterface;
use Yepr\Gen\Core\Template\TwigRenderer;

/**
 * A Drupal 10 or 11 module.
 *
 * The third target, and the one that turns 4.4's result from an argument into a
 * measurement. WordPress proved the seam was in the right place by being as
 * unlike Joomla as a PHP CMS gets. Drupal is the case 4.4 deliberately did
 * *not* take: another PHP framework with entities, a service container and
 * classes found by namespace, close enough to Joomla that a badly placed
 * abstraction could have fitted anyway - so if the abstraction had been wrong,
 * WordPress would have shown it and Drupal would have hidden it.
 *
 * Adding it after the fact costs what it should: three generators, seven
 * templates, one line in `Targets`, and nothing anywhere else.
 *
 * **What the three targets disagree about, and it is not the columns.** A
 * schema in Joomla is a file run once and recorded in `#__schemas`; in
 * WordPress a `dbDelta()` call re-run on every activation; in Drupal a PHP
 * array *describing* the tables, from which Drupal builds statements for
 * whichever driver the site uses. A screen in Joomla is an MVC triple found by
 * name, in WordPress a callback on a hook, in Drupal a route in YAML pointing
 * at a controller. Three answers each time, and what survives all three is the
 * model: entities, fields, pages.
 *
 * *The one thing all three targets share besides the model* is
 * `ProjectValidator`, and that keeps being the right answer: no component name,
 * no entities, no pages make a model unusable wherever it is going.
 *
 * @since  1.4.0
 */
final class DrupalTarget implements TargetInterface
{
    /**
     * The generators, in the order they run.
     *
     * Nothing accumulates across them, so this is the order somebody reads a
     * module in: what it is, what it stores, what it shows.
     *
     * @var class-string<GeneratorInterface>[]
     *
     * @since  1.4.0
     */
    public const GENERATORS = [
        ModuleGeneral::class,
        Schema::class,
        AdminScreens::class,
    ];

    /**
     * @param  string   $templateRoot    Directory holding generator_templates/Drupal.
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
        return 'drupal';
    }

    /**
     * How this target is named to somebody choosing one.
     *
     * @since  1.4.0
     */
    public function label(): string
    {
        return 'Drupal module';
    }

    /**
     * What a model must satisfy before generating a module from it.
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
        // Drupal module's strings go through `$this->t()` in the file that uses
        // them. The same unused seam `WordPressTarget` reported, now seen twice
        // - which says it is the Joomla target's, rather than every target's.
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
        return rtrim($this->templateRoot, '/\\') . '/Drupal';
    }
}
