<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\LanguageStringUtil;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Target\Joomla6Target;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Component\Extengen\Tests\Support\RecordingRenderer;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Template\TwigRenderer;

/**
 * Every file in the template set is one some generator asks for.
 *
 * 145 of 174 were not. They were what the set was started from - the Akeeba ATS
 * source it was copied out of - plus scaffolding for extension types this
 * project does not generate: eleven plugin groups, a module, a whole site
 * template. Nothing referred to any of it and nothing said so either, so
 * reading the template set meant reading five times more than it contained.
 *
 * The check is a generation run with a renderer that records what it is handed,
 * not a search for file names in the generator source. The generators build
 * template names by concatenation - `'Admin' . $pageType . $MVCtype . '.php.twig'`
 * - and a grep cannot see those.
 *
 * That makes the golden models load-bearing in a second way. A template only
 * reachable through a feature none of them uses counts as unreachable here, and
 * the honest answer to that is a fixture exercising the feature, not an entry
 * in the exception list below.
 */
final class TemplateReachabilityTest extends TestCase
{
    /**
     * In the set for a reason other than being rendered.
     */
    private const NOT_TEMPLATES = [
        // The GPL2 text that came with the borrowed templates. Removing a
        // license file from borrowed material is not a cleanup decision.
        'LICENSE',
    ];

    public function testNoTemplateIsUnreachable(): void
    {
        $unreachable = array_values(array_diff($this->onDisk(), $this->rendered()));

        $this->assertSame(
            [],
            $unreachable,
            'Nothing renders these: ' . implode(', ', $unreachable)
        );
    }

    /**
     * The other direction is already covered by Twig, which throws on a name
     * that resolves to nothing. This only has to show the run happened, so that
     * a rule comparing two empty lists cannot pass quietly.
     */
    public function testTheRunThatTheRuleReadsActuallyRendered(): void
    {
        $this->assertNotEmpty($this->rendered());
    }

    /**
     * Template paths asked for across every golden model.
     *
     * @return string[]
     */
    private function rendered(): array
    {
        static $paths = null;

        if ($paths !== null) {
            return $paths;
        }

        // The harness owns the Joomla constants the generators still read.
        GenerationHarness::run(json_decode(
            (string) file_get_contents($this->firstModel()),
            false,
            512,
            JSON_THROW_ON_ERROR
        ));

        $target             = new Joomla6Target($this->templateRoot());
        $languageStringUtil = new LanguageStringUtil();

        $inner = TwigRenderer::forDirectories($target->templateSetRoot());
        $inner->addExtension($languageStringUtil);

        $recorder = new RecordingRenderer($inner);

        // The generator list comes from the target, so this cannot drift out of
        // step with what the component actually runs.
        $generators = array_map(
            static fn (string $class): object => new $class($recorder, $languageStringUtil),
            Joomla6Target::GENERATORS
        );

        foreach ($this->models() as $model) {
            $project = Project::fromJson((string) file_get_contents($model));
            $files   = new FileCollection();

            foreach ($generators as $generator) {
                $generator->generate($project, $files);
            }
        }

        return $paths = $recorder->rendered();
    }

    /** @return string[] */
    private function models(): array
    {
        $models = glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: [];

        sort($models);

        return $models;
    }

    private function firstModel(): string
    {
        return $this->models()[0];
    }

    /** @return string[] */
    private function onDisk(): array
    {
        $root  = $this->templateRoot() . '/Joomla6';
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));

            if (!\in_array($relative, self::NOT_TEMPLATES, true)) {
                $files[] = $relative;
            }
        }

        sort($files);

        return $files;
    }

    private function templateRoot(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/generator_templates';
    }
}
