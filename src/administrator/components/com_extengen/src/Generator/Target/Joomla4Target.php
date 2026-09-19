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

use Yepr\Component\Extengen\Administrator\Generator\Joomla4\AdminEntities;
use Yepr\Component\Extengen\Administrator\Generator\Joomla4\AdminGeneral;
use Yepr\Component\Extengen\Administrator\Generator\Joomla4\AdminMVC;
use Yepr\Component\Extengen\Administrator\Generator\Joomla4\ComponentGeneral;
use Yepr\Component\Extengen\Administrator\Generator\Joomla4\Forms;
use Yepr\Component\Extengen\Administrator\Generator\Joomla4\LanguageFiles;
use Yepr\Component\Extengen\Administrator\Generator\Joomla4\SiteMVC;
use Yepr\Component\Extengen\Administrator\Generator\LanguageStringUtil;
use Yepr\Component\Extengen\Administrator\Generator\Model\ProjectValidator;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Gen\Core\Target\TargetInterface;
use Yepr\Gen\Core\Template\TwigRenderer;

/**
 * A Joomla component, as the generators write one today.
 *
 * Named for the template set it uses - `generator_templates/Joomla4` - rather
 * than for where the project is going. Step 1.8 sweeps that output for Joomla 6
 * and renames this with it; calling it `joomla6` now would be a claim about
 * output that has not been made yet.
 *
 * **The order is the definition.** `LanguageFiles` runs last because every other
 * generator adds language strings while its templates render, so the set is only
 * complete once they have finished. The rest run in the order `GenerateModel`
 * called them in, because that is the order the approved output was produced in.
 *
 * @since  0.9.0
 */
final class Joomla4Target implements TargetInterface
{
    /**
     * @param  string   $templateRoot    Directory holding generator_templates/Joomla4.
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
        return 'joomla4';
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
    public function validator(): ?ValidatorInterface
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
        $languageStringUtil = new LanguageStringUtil();

        // The shared renderer, with `strict_variables` on: a mistyped name is
        // an error rather than an empty string in a generated file. Getting
        // here is what step 1.8 was for.
        $renderer = TwigRenderer::forDirectories($this->templateSetRoot(), $this->cacheDirectory);
        $renderer->addExtension($languageStringUtil);

        return array_map(
            static fn (string $class): GeneratorInterface
                => new $class('Joomla4', $renderer, $languageStringUtil),
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
        return rtrim($this->templateRoot, '/\\') . '/Joomla4';
    }
}
