<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Target\Targets;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Pipeline;

/**
 * A second target, and what it proves: step 4.4.
 *
 * 0.4 introduced `TargetInterface` and was *done when a second, trivial fake
 * target can be registered and run without touching the pipeline*. A fake
 * target proves the wiring and nothing about whether the seam is in the right
 * place - it agrees with the first target about everything, because somebody
 * wrote it to.
 *
 * WordPress is the disagreeable one, which is why it was picked over Drupal:
 * Drupal is another PHP framework with entities, a service container and
 * annotated classes, close enough to Joomla that a badly placed abstraction
 * would still fit. WordPress has no MVC, no XML forms, no namespaces by
 * convention, no manifest and no schema installer. It has a header comment and
 * a list of hooks.
 *
 * So the rules below are about what did *not* have to change.
 *
 * @since  1.4.0
 */
final class SecondTargetTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function templates(): string
    {
        return $this->root() . '/src/administrator/components/com_extengen/generator_templates';
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function models(): array
    {
        $names = [];

        foreach (glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: [] as $path) {
            $names[] = [basename($path, '.json')];
        }

        return $names;
    }

    private function generate(string $name, string $targetId = 'wordpress'): FileCollection
    {
        if (!\defined('JPATH_ROOT')) {
            \define('JPATH_ROOT', $this->root() . '/src');
        }

        $ast = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        );

        $target = Targets::registry($this->templates())->get($targetId);

        return (new Pipeline())->run(Project::fromObject($ast), $target);
    }

    /**
     * There are two, and they are not each other.
     */
    public function testBothTargetsAreRegistered(): void
    {
        $targets = Targets::registry($this->templates());

        $this->assertSame(['joomla6', 'wordpress'], $targets->ids());
        $this->assertTrue($targets->has(Targets::DEFAULT));
        $this->assertNotSame(
            $targets->get('joomla6')->label(),
            $targets->get('wordpress')->label()
        );
    }

    /**
     * The same model produces a WordPress plugin.
     *
     * Over every fixture, including the model of Exten-gen itself, because a
     * target that worked for one shape of model and not another would be a
     * target with the first model's assumptions in it.
     */
    #[DataProvider('models')]
    public function testEveryModelProducesAPlugin(string $name): void
    {
        $files = $this->generate($name);
        $paths = $files->paths();

        $this->assertNotSame([], $paths, $name . ' produced nothing.');

        // What makes a directory a plugin, as WordPress reads it: a file whose
        // header comment carries Plugin Name, and an uninstall script it
        // includes by that exact name.
        $main = array_values(array_filter(
            $paths,
            static fn (string $p): bool => substr_count($p, '/') === 1 && str_ends_with($p, '.php')
                && !str_contains($p, 'uninstall')
        ));

        $this->assertCount(1, $main, $name . ' has no single main plugin file.');
        $this->assertStringContainsString('Plugin Name:', $files->get($main[0]));

        $slug = explode('/', $main[0])[0];

        $this->assertTrue($files->has($slug . '/uninstall.php'));
        $this->assertTrue($files->has($slug . '/readme.txt'));
        $this->assertTrue($files->has($slug . '/includes/class-' . $slug . '-activator.php'));
    }

    /**
     * And it is loadable PHP with nothing of the template left in it.
     */
    #[DataProvider('models')]
    public function testEveryGeneratedFileParses(string $name): void
    {
        $checked = 0;

        foreach ($this->generate($name)->all() as $path => $contents) {
            foreach (['{{', '{%'] as $tag) {
                $this->assertStringNotContainsString($tag, $contents, $path . ' still holds ' . $tag);
            }

            if (!str_ends_with($path, '.php')) {
                continue;
            }

            try {
                // For the ParseError, not the tokens: TOKEN_PARSE makes
                // token_get_all raise on bad syntax, which is `php -l` without
                // a subprocess per file.
                $this->assertNotSame([], token_get_all($contents, \TOKEN_PARSE));
            } catch (\ParseError $e) {
                $this->fail($path . ' does not parse: ' . $e->getMessage());
            }

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, $name . ' generated no PHP to check.');
    }

    /**
     * A schema is not a file here, and that is the finding.
     *
     * The two CMSs do not disagree about the columns; they disagree about what
     * a schema *is*. Joomla runs `sql/install.mysql.utf8.sql` once and records
     * it in `#__schemas`; a WordPress plugin calls `dbDelta()` from its
     * activation hook with a CREATE TABLE statement, which WordPress compares
     * against the database and alters - so the same statement is the install
     * and every upgrade after it.
     *
     * A generator that had assumed "a schema is a .sql file" would have had
     * that assumption above the target, and this is the test that would have
     * found it.
     */
    public function testASchemaIsAnActivationHookRatherThanAFile(): void
    {
        $wordpress = $this->generate('conference');
        $joomla    = $this->generate('conference', 'joomla6');

        $this->assertTrue(
            $joomla->has('administrator/components/com_conference/sql/install.mysql.utf8.sql'),
            'The Joomla target stopped writing install SQL, which changes what this test compares.'
        );

        foreach ($wordpress->paths() as $path) {
            $this->assertStringEndsNotWith('.sql', $path, 'The WordPress target wrote a SQL file.');
        }

        $activator = $wordpress->get('conference/includes/class-conference-activator.php');

        $this->assertStringContainsString('dbDelta(', $activator);
        $this->assertStringContainsString('register_activation_hook', $wordpress->get('conference/conference.php'));
    }

    /**
     * The two targets share what is about models and nothing that is about a CMS.
     *
     * This is the claim 4.4 exists to make. The WordPress generators may reach
     * for the model, the shared engine and their own namespace - not for
     * anything under `Generator\Joomla6`, and not for a Joomla class. A target
     * that had to borrow from the first one would mean the first one was never
     * a target, only the code with a different name on it.
     */
    public function testTheWordPressTargetBorrowsNothingFromTheJoomlaOne(): void
    {
        $directory = $this->root()
            . '/src/administrator/components/com_extengen/src/Generator/WordPress';

        $checked = 0;

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/^\s*use\s+.*Generator\\\\Joomla6\\\\/mi',
                $source,
                basename($file) . ' imports from the Joomla target.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/^\s*use\s+Joomla\\\\/mi',
                $source,
                basename($file) . ' imports a Joomla class.'
            );

            $checked++;
        }

        $this->assertGreaterThan(2, $checked, 'There is hardly a WordPress target to check.');
    }

    /**
     * Neither does its template set.
     *
     * The templates are where a leak would actually happen, because a template
     * is copied from a neighbouring one far more often than a class is.
     */
    public function testTheTemplateSetsDoNotOverlap(): void
    {
        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->templates() . '/WordPress', \FilesystemIterator::SKIP_DOTS)
        );

        $checked = 0;

        foreach ($tree as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach (['_JEXEC', 'Joomla\\', 'JPATH_'] as $joomlaism) {
                $this->assertStringNotContainsString(
                    $joomlaism,
                    $source,
                    $file->getFilename() . ' carries ' . $joomlaism . ' into a WordPress plugin.'
                );
            }

            $checked++;
        }

        $this->assertGreaterThan(5, $checked, 'There is hardly a template set to check.');
    }
}
