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

use Yepr\Component\Extengen\Administrator\Generator\Joomla6\AdminEntities;
use Yepr\Component\Extengen\Administrator\Generator\Joomla6\AdminGeneral;
use Yepr\Component\Extengen\Administrator\Generator\Joomla6\AdminMVC;
use Yepr\Component\Extengen\Administrator\Generator\Joomla6\ComponentGeneral;
use Yepr\Component\Extengen\Administrator\Generator\Joomla6\Forms;
use Yepr\Component\Extengen\Administrator\Generator\Joomla6\LanguageFiles;
use Yepr\Component\Extengen\Administrator\Generator\Joomla6\SiteMVC;
use Yepr\Component\Extengen\Administrator\Generator\LanguageStringUtil;
use Yepr\Component\Extengen\Administrator\Generator\Model\ProjectValidator;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Gen\Core\Target\TargetInterface;
use Yepr\Gen\Core\Template\TwigRenderer;

/**
 * A Joomla 6 component.
 *
 * This was `Joomla4Target` until 1.8, named for the template set rather than
 * for where the project was going, because calling it six would have been a
 * claim about output nobody had made. 1.8 made it: the generated code calls
 * Joomla 6 APIs and none of the ones Joomla 6 keeps only for the
 * `behaviour - compat6` plugin, so the name is a statement now rather than a
 * hope.
 *
 * **The order is the definition.** `LanguageFiles` runs last because every other
 * generator adds language strings while its templates render, so the set is only
 * complete once they have finished. The rest run in the order `GenerateModel`
 * called them in, because that is the order the approved output was produced in.
 *
 * @since  0.9.0
 */
final class Joomla6Target implements TargetInterface
{
    /**
     * @param  string   $templateRoot    Directory holding generator_templates/Joomla6.
     * @param  ?string  $cacheDirectory  Where Twig may cache compiled templates.
     *
     * @since  0.9.0
     */
    public function __construct(
        private readonly string $templateRoot,
        private readonly ?string $cacheDirectory = null
    ) {
    }

    /**
     * The stable identifier for this target.
     *
     * @since  0.9.0
     */
    public function id(): string
    {
        return 'joomla6';
    }

    /**
     * How this target is named to somebody choosing one.
     *
     * @since  0.9.0
     */
    public function label(): string
    {
        return 'Joomla component';
    }

    /**
     * What a project must satisfy before this target will generate from it.
     *
     * @since  0.9.0
     */
    public function validator(): ValidatorInterface
    {
        return new ProjectValidator();
    }

    /**
     * The generators, in the order they run.
     *
     * A constant rather than a list built inside `generators()`, so that the
     * order - which is the definition, see the class comment - has one home,
     * and a test that has to drive these with a renderer of its own does not
     * restate it.
     *
     * @var class-string<GeneratorInterface>[]
     *
     * @since  0.9.0
     */
    public const GENERATORS = [
        ComponentGeneral::class,
        AdminGeneral::class,
        AdminEntities::class,
        AdminMVC::class,
        Forms::class,
        SiteMVC::class,
        // Last: it needs every string the others collected.
        LanguageFiles::class,
    ];

    /**
     * The generators, wired to one renderer.
     *
     * They share one `LanguageStringUtil`: it is both a Twig extension the
     * templates call and the place the strings accumulate, so a generator with
     * its own copy would collect strings nobody ever writes out.
     *
     * @return GeneratorInterface[]
     *
     * @since  0.9.0
     */
    public function generators(): array
    {
        // The renderer first, and not for tidiness: LanguageStringUtil is a Twig
        // extension, so constructing it loads Twig\Extension\AbstractExtension
        // - and on a Joomla site the only thing that puts Twig on the autoload
        // path is TwigRenderer, which requires the library's own vendor
        // autoloader when it is first asked for a renderer. Built the other way
        // round, generation died with "Class Twig\Extension\AbstractExtension
        // not found" on a real site while passing every test here, because the
        // suite's autoloader has Twig in it from composer.
        //
        // With `strict_variables` on: a mistyped name is an error rather than
        // an empty string in a generated file.
        $renderer = TwigRenderer::forDirectories($this->templateSetRoot(), $this->cacheDirectory);

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
     * @since  0.9.0
     */
    public function templateSetRoot(): string
    {
        return rtrim($this->templateRoot, '/\\') . '/Joomla6';
    }
}
