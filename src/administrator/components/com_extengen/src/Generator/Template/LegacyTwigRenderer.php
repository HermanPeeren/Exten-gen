<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Template;

use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;
use Yepr\Gen\Core\Template\RendererInterface;

/**
 * Renders the existing templates, with the settings they were written under.
 *
 * The shared library's `TwigRenderer` turns `strict_variables` on, because a
 * mistyped name rendering as an empty string produces a broken file that looks
 * fine. These templates have never run that way, and they would not survive it:
 * nineteen of them read `company_namespace` while one reads `companyNamepace`,
 * and each generator supplies a different set of variables to templates that
 * share a directory. Turning it on here would throw on templates that today
 * quietly render an empty string, which is a change to the output — and the
 * whole point of this step is that the output does not change.
 *
 * So the flag stays off until step 1.8, where the templates are swept for
 * Joomla 6 and can be made to deserve it. **This class is deleted then**, and
 * the shared renderer takes over.
 *
 * It keeps two other habits of the original deliberately:
 *
 * - One `Environment` per template *directory*, because the generators address
 *   templates by bare filename and rely on the loader being rooted at the
 *   directory they are asking about.
 * - No output normalisation. The shared renderer trims to one trailing newline;
 *   these templates were captured without that, and normalising would change
 *   every file in the baseline.
 *
 * @since  0.9.0
 */
final class LegacyTwigRenderer implements RendererInterface
{
    /**
     * One environment per template directory, built on first use.
     *
     * @var array<string, Environment>
     *
     * @since  0.9.0
     */
    private array $environments = [];

    /**
     * @param  string              $templateRoot  Directory holding the template set.
     * @param  ?string             $cacheDirectory  Where Twig may cache; null to compile each run.
     * @param  ExtensionInterface[] $extensions   Extensions every environment gets.
     *
     * @since  0.9.0
     */
    public function __construct(
        private readonly string $templateRoot,
        private readonly ?string $cacheDirectory = null,
        private readonly array $extensions = []
    ) {
    }

    /**
     * Render a template.
     *
     * @param  string               $template   Path within the template root, ending in the file name.
     * @param  array<string, mixed> $variables  Variables for the template.
     *
     * @since  0.9.0
     */
    public function render(string $template, array $variables = []): string
    {
        $directory = trim(\dirname($template), './');
        $name      = basename($template);

        return $this->environment($directory)->render($name, $variables);
    }

    /**
     * The environment rooted at one template directory.
     *
     * @since  0.9.0
     */
    private function environment(string $directory): Environment
    {
        if (isset($this->environments[$directory])) {
            return $this->environments[$directory];
        }

        $twig = new Environment(
            new FilesystemLoader(rtrim($this->templateRoot, '/\\') . '/' . $directory),
            [
                'cache' => $this->cacheDirectory ?? false,
                // Deliberately left at Twig's defaults. See the class comment.
            ]
        );

        foreach ($this->extensions as $extension) {
            $twig->addExtension($extension);
        }

        return $this->environments[$directory] = $twig;
    }
}
