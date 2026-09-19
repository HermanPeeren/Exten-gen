<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Support;

use Yepr\Component\Extengen\Administrator\Generator\Generator;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Target\Joomla4Target;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Target\Target;

/**
 * Runs a project through the pipeline, exactly as the component does.
 *
 * This replaced `LegacyGeneratorRunner`, which had to make a Joomla-shaped
 * directory in a temp folder, let the generators write into it, and read the
 * result back — because they wrote to disk and there was no other way to see
 * what they had produced.
 *
 * None of that is needed now. Generation returns a `FileCollection`, so the
 * harness is the same three lines the component runs, and the only thing it
 * still needs from a filesystem is somewhere for Twig to find its templates.
 *
 * The one Joomla constant the generators still read is `JPATH_ROOT`, and only
 * for building those template paths. Step 1.8 is where that goes; until then
 * defining it is the whole of the bootstrap.
 */
final class GenerationHarness
{
    private static bool $bootstrapped = false;

    /** Generate for a decoded project. */
    public static function run(object $ast): FileCollection
    {
        self::bootstrap();

        $target = new Joomla4Target(self::componentRoot() . '/generator_templates');

        return (new Pipeline())->run(
            Project::fromObject($ast),
            new Target($target->id(), $target->label(), $target->validator(), ...$target->generators())
        );
    }

    /** Generate for a project stored as JSON. */
    public static function runJson(string $json): FileCollection
    {
        self::bootstrap();

        $target = new Joomla4Target(self::componentRoot() . '/generator_templates');

        return (new Pipeline())->run(
            Project::fromJson($json),
            new Target($target->id(), $target->label(), $target->validator(), ...$target->generators())
        );
    }

    /**
     * Paths any generator wrote more than once, and how often.
     *
     * Two pages can derive the same file name, and the last write wins — which
     * is what `fopen(..., 'w')` always did, silently. Generating into a
     * collection made it observable; this is how a test observes it.
     *
     * @return array<string, int>
     */
    public static function collisions(object $ast): array
    {
        self::bootstrap();

        $project    = Project::fromObject($ast);
        $target     = new Joomla4Target(self::componentRoot() . '/generator_templates');
        $files      = new FileCollection();
        $collisions = [];

        foreach ($target->generators() as $generator) {
            $generator->generate($project, $files);

            // Counting overwrites is this project's own habit, not something the
            // shared interface promises - so it is asked for where it exists,
            // rather than widening GeneratorInterface for a test.
            if ($generator instanceof Generator) {
                $collisions += $generator->overwrittenFiles();
            }
        }

        ksort($collisions);

        return $collisions;
    }

    private static function componentRoot(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen';
    }

    private static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        if (!\defined('JPATH_ROOT')) {
            // Still read by the generators when they build template paths, and
            // by nothing else. Anything under it that they would have written
            // to now goes into the collection instead.
            \define('JPATH_ROOT', \dirname(__DIR__, 2) . '/src');
        }

        if (!\defined('JPATH_LIBRARIES')) {
            \define('JPATH_LIBRARIES', \dirname(__DIR__, 2) . '/src/libraries');
        }

        self::$bootstrapped = true;
    }
}
