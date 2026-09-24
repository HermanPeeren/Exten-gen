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
 * The targets that are not Joomla, and what they prove: step 4.4.
 *
 * 0.4 introduced `TargetInterface` and was *done when a second, trivial fake
 * target can be registered and run without touching the pipeline*. A fake
 * target proves the wiring and nothing about whether the seam is in the right
 * place - it agrees with the first target about everything, because somebody
 * wrote it to.
 *
 * **WordPress was picked for 4.4 because it disagrees.** No MVC, no XML forms,
 * no namespaces by convention, no manifest and no schema installer: a header
 * comment and a list of hooks. Drupal was the case 4.4 deliberately did *not*
 * take, being another PHP framework with entities, a container and classes
 * found by namespace - close enough to Joomla that a badly placed abstraction
 * could have fitted anyway.
 *
 * **It is here now, and adding it cost one line in `Targets`**, three
 * generators and seven templates. That number is the answer to whether the
 * second target proved anything: if the seam had been in the wrong place, the
 * third would have needed the pipeline, the model or the base class to move.
 *
 * @since  1.4.0
 */
final class SecondTargetTest extends TestCase
{
    /**
     * Everything that is not the default, and what a module of it is called.
     *
     * @var array<string, array<string, string>>
     */
    private const OTHERS = [
        'wordpress' => ['extension' => '.php', 'manifest' => 'Plugin Name:'],
        'drupal'    => ['extension' => '.info.yml', 'manifest' => 'type: module'],
    ];

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
        $cases = [];

        foreach (glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: [] as $path) {
            foreach (array_keys(self::OTHERS) as $target) {
                $cases[] = [basename($path, '.json'), $target];
            }
        }

        return $cases;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function targets(): array
    {
        return array_map(static fn (string $id): array => [$id], array_keys(self::OTHERS));
    }

    private function generate(string $name, string $targetId): FileCollection
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

        return (new Pipeline())->run(
            Project::fromObject($ast),
            Targets::registry($this->templates())->get($targetId)
        );
    }

    /**
     * There are three, and none of them is another.
     */
    public function testEveryTargetIsRegistered(): void
    {
        $targets = Targets::registry($this->templates());

        $this->assertSame(['drupal', 'joomla6', 'wordpress'], $targets->ids());
        $this->assertTrue($targets->has(Targets::DEFAULT));

        $labels = array_map(
            static fn (string $id): string => $targets->get($id)->label(),
            $targets->ids()
        );

        $this->assertSame($labels, array_unique($labels), 'Two targets are named the same thing.');
    }

    /**
     * Every model produces something the CMS would recognise.
     *
     * Over every fixture, including the model of Exten-gen itself, because a
     * target that worked for one shape of model and not another would be a
     * target with the first model's assumptions in it.
     */
    #[DataProvider('models')]
    public function testEveryModelProducesAnExtension(string $name, string $targetId): void
    {
        $files = $this->generate($name, $targetId);
        $paths = $files->paths();

        $this->assertNotSame([], $paths, $name . ' produced nothing for ' . $targetId . '.');

        // Everything is under one directory named for the project, which is
        // what both CMSs mean by an extension.
        $roots = array_unique(array_map(
            static fn (string $p): string => explode('/', $p)[0],
            $paths
        ));

        $this->assertCount(1, $roots, $targetId . ' scattered its output over more than one root.');

        // And that directory holds the one file the CMS reads to decide the
        // extension exists at all: a plugin header, or an info file named for
        // the directory it is in. Both fail the same way when they are wrong -
        // the extension is simply not in the list, with no error.
        $manifest = $roots[0] . '/' . $roots[0] . self::OTHERS[$targetId]['extension'];

        $this->assertTrue($files->has($manifest), $targetId . ' has no ' . $manifest . '.');
        $this->assertStringContainsString(
            self::OTHERS[$targetId]['manifest'],
            $files->get($manifest)
        );
    }

    /**
     * And it is loadable PHP with nothing of the template left in it.
     */
    #[DataProvider('models')]
    public function testEveryGeneratedFileParses(string $name, string $targetId): void
    {
        $checked = 0;

        foreach ($this->generate($name, $targetId)->all() as $path => $contents) {
            foreach (['{{', '{%'] as $tag) {
                $this->assertStringNotContainsString($tag, $contents, $path . ' still holds ' . $tag);
            }

            // Drupal's schema and hooks live in a `.install`, which is PHP with
            // a name that does not say so.
            if (!str_ends_with($path, '.php') && !str_ends_with($path, '.install')) {
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

        $this->assertGreaterThan(0, $checked, $name . ' generated no PHP to check for ' . $targetId . '.');
    }

    /**
     * The YAML is YAML, as far as anything here can tell.
     *
     * Not through a parser: neither `ext-yaml` nor `symfony/yaml` is installed,
     * and pulling in a dependency to read six generated files would be a larger
     * change than the thing it checks. So this asserts the one mistake that is
     * actually likely - a tab, which YAML forbids in indentation and which
     * arrives from a template sitting beside tab-indented PHP - plus even
     * indentation and no duplicate keys at the top level, which is what a
     * generated routing file gets wrong when two pages derive one name.
     */
    #[DataProvider('models')]
    public function testEveryGeneratedYamlFileIsWellFormed(string $name, string $targetId): void
    {
        $checked = 0;

        foreach ($this->generate($name, $targetId)->all() as $path => $contents) {
            if (!str_ends_with($path, '.yml')) {
                continue;
            }

            $this->assertStringNotContainsString("\t", $contents, $path . ' indents with a tab.');

            $keys = [];

            foreach (explode("\n", $contents) as $number => $line) {
                if (trim($line) === '') {
                    continue;
                }

                $indent = \strlen($line) - \strlen(ltrim($line, ' '));

                $this->assertSame(
                    0,
                    $indent % 2,
                    $path . ' line ' . ($number + 1) . ' is indented by ' . $indent . '.'
                );

                if ($indent === 0 && preg_match('/^([^:]+):/', $line, $key) === 1) {
                    $keys[] = $key[1];
                }
            }

            $this->assertSame(
                $keys,
                array_values(array_unique($keys)),
                $path . ' declares the same top-level key twice.'
            );

            $checked++;
        }

        if ($targetId === 'drupal') {
            $this->assertGreaterThan(3, $checked, 'A Drupal module with no YAML in it is not a module.');

            return;
        }

        // WordPress has no configuration files at all - everything is a hook
        // call in PHP - so finding one here would mean a template had been
        // copied across from the target next door.
        $this->assertSame(0, $checked, 'A WordPress plugin does not carry YAML.');
    }

    /**
     * Three targets, three answers to what a schema is - and that is the finding.
     *
     * They do not disagree about the columns. They disagree about what a schema
     * *is*. Joomla writes `sql/install.mysql.utf8.sql`, runs it once and records
     * the version in `#__schemas`. A WordPress plugin writes a `dbDelta()` call
     * in its activation hook, re-run on every activation, so the same statement
     * is the install and every upgrade after it. A Drupal module writes neither:
     * `hook_schema()` returns a PHP array *describing* the tables, and Drupal
     * builds the statements itself for whichever driver the site runs on.
     *
     * Two targets could have been a coincidence - one file format against
     * another. Three is the shape of the thing, and what survives all three is
     * the columns, which is what an ER1 model holds.
     */
    public function testEachTargetHasItsOwnIdeaOfWhatASchemaIs(): void
    {
        $joomla = $this->generate('conference', 'joomla6');

        $this->assertTrue(
            $joomla->has('administrator/components/com_conference/sql/install.mysql.utf8.sql'),
            'The Joomla target stopped writing install SQL, which changes what this compares.'
        );

        $wordpress = $this->generate('conference', 'wordpress');

        $this->assertStringContainsString(
            'dbDelta(',
            $wordpress->get('conference/includes/class-conference-activator.php')
        );
        $this->assertStringContainsString(
            'register_activation_hook',
            $wordpress->get('conference/conference.php')
        );

        $drupal  = $this->generate('conference', 'drupal');
        $install = $drupal->get('conference/conference.install');

        $this->assertStringContainsString('function conference_schema(): array', $install);
        $this->assertStringContainsString("'type' => 'serial'", $install);

        // And neither of the other two wrote SQL, which is the half that is
        // easy to get wrong by copying the first target's generator.
        foreach ([$wordpress, $drupal] as $files) {
            foreach ($files->paths() as $path) {
                $this->assertStringEndsNotWith('.sql', $path, $path . ' is a SQL file outside Joomla.');
            }
        }

        // The columns, though, are the same in all three, and they are read out
        // of the model rather than named here - a list written in this file
        // would be a fourth place for them to be wrong.
        $sql = $joomla->get('administrator/components/com_conference/sql/install.mysql.utf8.sql');

        foreach ($this->propertyNames('conference') as $column) {
            $this->assertStringContainsString($column, $sql, 'The Joomla schema lost ' . $column);
            $this->assertStringContainsString($column, $install, 'The Drupal schema lost ' . $column);
        }
    }

    /**
     * Every property a model's entities carry, by name.
     *
     * @return string[]
     */
    private function propertyNames(string $name): array
    {
        $model = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $names = [];

        foreach ($model['datamodel'] ?? [] as $entity) {
            foreach ($entity['field'] ?? [] as $field) {
                if (($field['field_type'] ?? '') === 'property') {
                    $names[] = strtolower((string) $field['field_name']);
                }
            }
        }

        $this->assertNotSame([], $names, $name . ' has no properties, so this compares nothing.');

        return array_unique($names);
    }

    /**
     * A target borrows nothing from another target.
     *
     * This is the claim the step exists to make. A target's generators may
     * reach for the model, the shared engine and their own namespace - not for
     * another target's classes, and not for another CMS's. A target that had to
     * borrow would mean the first one was never a target, only the code with a
     * different name on it.
     */
    #[DataProvider('targets')]
    public function testATargetBorrowsNothingFromAnother(string $targetId): void
    {
        $directory = $this->root() . '/src/administrator/components/com_extengen/src/Generator/'
            . ucfirst($targetId === 'wordpress' ? 'WordPress' : $targetId);

        $checked = 0;

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            foreach (['Generator\\\\Joomla6', 'Generator\\\\WordPress', 'Generator\\\\Drupal'] as $namespace) {
                if (str_contains($namespace, ucfirst($targetId === 'wordpress' ? 'WordPress' : $targetId))) {
                    continue;
                }

                $this->assertDoesNotMatchRegularExpression(
                    '/^\s*use\s+.*' . $namespace . '\\\\/mi',
                    $source,
                    basename($file) . ' imports from ' . $namespace . '.'
                );
            }

            $this->assertDoesNotMatchRegularExpression(
                '/^\s*use\s+Joomla\\\\/mi',
                $source,
                basename($file) . ' imports a Joomla class.'
            );

            $checked++;
        }

        $this->assertGreaterThan(2, $checked, 'There is hardly a ' . $targetId . ' target to check.');
    }

    /**
     * Neither does its template set.
     *
     * The templates are where a leak would actually happen, because a template
     * is copied from a neighbouring one far more often than a class is.
     */
    #[DataProvider('targets')]
    public function testTheTemplateSetsDoNotOverlap(string $targetId): void
    {
        $set = $this->templates() . '/' . ($targetId === 'wordpress' ? 'WordPress' : ucfirst($targetId));

        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($set, \FilesystemIterator::SKIP_DOTS)
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
                    $file->getFilename() . ' carries ' . $joomlaism . ' into a ' . $targetId . ' extension.'
                );
            }

            $checked++;
        }

        $this->assertGreaterThan(5, $checked, 'There is hardly a ' . $targetId . ' template set to check.');
    }
}
