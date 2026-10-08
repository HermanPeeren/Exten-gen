<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Imported\GeneratorPackage;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Component\Extengen\Administrator\Generator\Target\Joomla6Target;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorEntry;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

/**
 * A generator imported from Gen-gen, read, checked and run: step 5.4.
 *
 * The claim worth testing is the one the import's safety rests on: an imported
 * generator is its rules and nothing else, and those rules can only name what
 * the target publishes. So the package is read, refused when it names anything
 * else, and then run through this component's own classes - and the output
 * differs from the built-in generator by exactly what the rules differ by.
 *
 * `generators.cy.js` imports the same kind of package through the screen.
 *
 * @since  1.3.0
 */
final class ImportedGeneratorTest extends TestCase
{
    private const RULES_PATH = 'administrator/components/com_extengen/src/Generator/Rules/joomla6.rules.json';

    /**
     * @var string[]
     */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function target(): Joomla6Target
    {
        if (!\defined('JPATH_ROOT')) {
            \define('JPATH_ROOT', \dirname(__DIR__, 2) . '/src');
        }

        return new Joomla6Target(\dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/generator_templates');
    }

    /**
     * The committed rules, optionally changed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rules(?callable $change = null): array
    {
        $rules = RuleSet::fromFile(RuleDrivenGenerator::defaultRuleFile())->toArray();

        return $change === null ? $rules : $change($rules);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @param  array<string, mixed>              $manifest  Overrides.
     */
    private function zip(array $rules, array $manifest = [], bool $withManifest = true): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gen') . '.zip';
        $zip  = new \ZipArchive();

        $this->temporary[] = $path;

        $this->assertTrue($zip->open($path, \ZipArchive::CREATE));

        if ($withManifest) {
            $zip->addFromString(GeneratorPackage::MANIFEST, (string) json_encode($manifest + [
                'format'       => 1,
                'name'         => 'Without a licence',
                'target'       => 'joomla6',
                'metalanguage' => ['key' => 'ER1', 'version' => ''],
                'rules'        => self::RULES_PATH,
            ]));
        }

        $zip->addFromString(self::RULES_PATH, RuleSet::fromArray($rules)->toJson());
        $zip->close();

        return $path;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function withoutLicense(): array
    {
        return $this->rules(static fn (array $rules): array => array_values(array_filter(
            $rules,
            static fn (array $rule): bool => $rule['id'] !== 'admin.general.license'
        )));
    }

    public function testAPackageIsReadFromItsManifest(): void
    {
        $package = GeneratorPackage::fromZip($this->zip($this->withoutLicense()));

        $this->assertSame('without-a-licence', $package->key, 'the key falls back to the name, slugged');
        $this->assertSame('Without a licence', $package->name);
        $this->assertSame('joomla6', $package->target);
        $this->assertSame('ER1', $package->metalanguageKey);
        $this->assertCount(\count($this->rules()) - 1, $package->rules);
    }

    public function testTheCommittedRulesHaveNoProblems(): void
    {
        // The vacuity guard for the next two: the checks pass what they should.
        $target  = $this->target();
        $package = GeneratorPackage::fromZip($this->zip($this->rules()));

        $this->assertSame([], $package->problems($target->vocabulary(), $target->rulePrefixes()));
        $this->assertSame(['component.', 'admin.general.', 'admin.entity.', 'admin.mvc.', 'site.mvc.'], $target->rulePrefixes());
    }

    public function testARuleNamingATemplateTheTargetHasNotGotIsRefused(): void
    {
        $target  = $this->target();
        $package = GeneratorPackage::fromZip($this->zip($this->rules(static function (array $rules): array {
            $rules[0]['template'] = '../../../../configuration.php';

            return $rules;
        })));

        $problems = $package->problems($target->vocabulary(), $target->rulePrefixes());

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('is not a template of joomla6', $problems[0]);
    }

    public function testARuleNoGeneratorRunsIsRefused(): void
    {
        $target  = $this->target();
        $package = GeneratorPackage::fromZip($this->zip($this->rules(static function (array $rules): array {
            $rules[0]['id'] = 'plugin.extra';

            return $rules;
        })));

        $problems = $package->problems($target->vocabulary(), $target->rulePrefixes());

        $this->assertSame(
            ['rule plugin.extra: no generator of joomla6 runs rules with this prefix, so it would never run.'],
            $problems
        );
    }

    public function testAZipWithoutAManifestSaysWhereToGetOne(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('There is no generator.json in this zip');

        GeneratorPackage::fromZip($this->zip($this->rules(), [], false));
    }

    public function testAnotherFormatIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('format 2');

        GeneratorPackage::fromZip($this->zip($this->rules(), ['format' => 2]));
    }

    /**
     * The point of it all: the imported rules are what runs.
     *
     * Every golden model, generated by the built-in generator and by the same
     * target running the imported rules. The only difference is the file the
     * missing rule wrote, and every other file is byte for byte the same.
     */
    public function testAnImportedRuleSetIsWhatTheTargetRuns(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rules') . '.json';

        $this->temporary[] = $file;

        file_put_contents($file, RuleSet::fromArray($this->withoutLicense())->toJson());

        $compared = 0;

        foreach (glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: [] as $model) {
            $builtIn  = $this->generate($model, $this->target())->all();
            $imported = $this->generate($model, $this->target()->withRules($file))->all();

            $missing = array_keys(array_diff_key($builtIn, $imported));

            $this->assertCount(1, $missing, basename($model));
            $this->assertStringEndsWith('/LICENSE.txt', $missing[0]);
            $this->assertSame([], array_keys(array_diff_key($imported, $builtIn)));

            foreach ($imported as $path => $contents) {
                $this->assertSame($builtIn[$path], $contents, $path);
            }

            $compared++;
        }

        $this->assertGreaterThan(0, $compared);
    }

    private function generate(string $model, Joomla6Target $target): FileCollection
    {
        $ast = json_decode((string) file_get_contents($model), false, 512, \JSON_THROW_ON_ERROR);

        return (new Pipeline())->run(Project::fromObject($ast), $target);
    }

    public function testAGeneratorAppliesToALanguageThatDerivesFromItsOwn(): void
    {
        $entry = GeneratorEntry::fromRow((object) [
            'gen_key'          => 'x',
            'name'             => 'X',
            'target'           => 'joomla6',
            'metalanguage_key' => 'ER1',
            'rules'            => '[]',
        ], 'Joomla component');

        $er1     = $this->language('ER1', '1.2');
        $derived = $this->language('DerivedER', '1.0');
        $other   = $this->language('Testlang', '1.0');

        $this->assertSame(GeneratorEntry::IMPORTED . 'x', $entry->id);
        $this->assertTrue($entry->appliesTo([$er1]));
        $this->assertTrue($entry->appliesTo([$derived, $er1]), 'ER1 is in the ancestry');
        $this->assertFalse($entry->appliesTo([$other]));

        $pinned = GeneratorEntry::fromRow((object) [
            'gen_key'              => 'y',
            'target'               => 'joomla6',
            'metalanguage_key'     => 'ER1',
            'metalanguage_version' => '1.1',
        ], '');

        $this->assertFalse($pinned->appliesTo([$er1]), 'a generator naming a version wants that version');
    }

    private function language(string $key, string $version): MetalanguageEntry
    {
        return MetalanguageEntry::fromRow((object) [
            'id'       => 1,
            'lang_key' => $key,
            'version'  => $version,
            'name'     => $key,
            'manifest' => '{}',
        ]);
    }
}
