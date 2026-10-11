<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Imported\GeneratorPackage;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Component\Extengen\Administrator\Generator\Rules\LedgerDerivations;
use Yepr\Component\Extengen\Administrator\Generator\Target\Joomla6Target;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorAncestry;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogue;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorEntry;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Gen\Core\Rule\Rule;
use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

/**
 * The first generator that extends another: the LedgerLang generator, step 5.9.
 *
 * The claim is the one language ancestry makes, one level up. LedgerLang adds
 * to ER1 and removes nothing, so ER1's generator still reads a LedgerLang
 * model. The LedgerLang generator adds to the Joomla 6 generator and removes
 * nothing, so it still produces every file the parent does, byte for byte, and
 * then the files for what LedgerLang added.
 *
 * @since  1.6.0
 */
final class LedgerLangGeneratorTest extends TestCase
{
    private const LEDGER = 'joomla6-ledgerlang';

    private function ledgerModel(): object
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden-joomla6-ledgerlang/models/balloonledger.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function entry(string $id, string $extends, array $rules = [], string $target = 'joomla6', string $language = 'ER1'): GeneratorEntry
    {
        return GeneratorEntry::fromPackage(GeneratorPackage::fromManifest(
            [
                'format'       => 2,
                'key'          => $id,
                'name'         => ucfirst($id),
                'target'       => $target,
                'metalanguage' => ['key' => $language, 'version' => ''],
                'extends'      => $extends,
            ],
            RuleSet::fromArray($rules)->toJson()
        ), 'Joomla component', true);
    }

    /**
     * @param  GeneratorEntry[]  $entries
     */
    private function ancestryOver(array $entries): GeneratorAncestry
    {
        return new GeneratorAncestry(static function (string $id) use ($entries): ?GeneratorEntry {
            foreach ($entries as $entry) {
                if ($entry->id === $id) {
                    return $entry;
                }
            }

            return null;
        });
    }

    private function language(string $key): MetalanguageEntry
    {
        return MetalanguageEntry::fromRow((object) [
            'id'       => 1,
            'lang_key' => $key,
            'version'  => '1.0',
            'name'     => $key,
            'manifest' => '{}',
        ]);
    }

    /**
     * @return string[]
     */
    private function ids(RuleSet $rules): array
    {
        return array_map(static fn (Rule $rule): string => $rule->id, iterator_to_array($rules));
    }

    public function testTheComponentShipsTheLedgerLangGeneratorAsAChildOfJoomla6(): void
    {
        $ledger = GenerationHarness::generator(self::LEDGER);

        $this->assertTrue($ledger->builtIn);
        $this->assertSame('joomla6', $ledger->extends);
        $this->assertSame('joomla6', $ledger->target);
        $this->assertSame('LedgerLang', $ledger->metalanguageKey);
        $this->assertSame(['joomla6'], array_map(
            static fn (GeneratorEntry $parent): string => $parent->id,
            GenerationHarness::ancestry()->of($ledger)
        ));
    }

    public function testItRunsEveryRuleOfItsParentAndThenItsOwn(): void
    {
        $parent = $this->ids(RuleSet::fromFile(RuleDrivenGenerator::defaultRuleFile()));
        $rules  = $this->ids(GenerationHarness::ancestry()->rules(GenerationHarness::generator(self::LEDGER)));

        $this->assertSame($parent, \array_slice($rules, 0, \count($parent)), 'every inherited rule, in its place');
        $this->assertSame(
            ['admin.general.ledger.roles', 'admin.general.ledger.line', 'admin.general.ledger.postingrules'],
            \array_slice($rules, \count($parent))
        );
    }

    /**
     * The test the claim rests on: the parent's output, untouched, plus the new files.
     */
    public function testALedgerLangProjectGetsItsParentsFilesByteForByteAndThreeMore(): void
    {
        $parent = GenerationHarness::run($this->ledgerModel());
        $child  = GenerationHarness::runWithGenerator($this->ledgerModel(), self::LEDGER);

        foreach ($parent as $path => $contents) {
            $this->assertSame($contents, $child->get($path), $path . ' differs from what the parent generates');
        }

        $this->assertSame(
            [
                'administrator/components/com_balloonledger/src/Ledger/AccountRoles.php',
                'administrator/components/com_balloonledger/src/Ledger/JournalLine.php',
                'administrator/components/com_balloonledger/src/Ledger/PostingRules.php',
            ],
            array_values(array_diff($child->paths(), $parent->paths()))
        );
    }

    /**
     * An ER1 project has no posting rules, so the added rules do not fire.
     */
    public function testAnEr1ProjectGetsExactlyWhatTheParentGives(): void
    {
        $model = (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/balloonplanning.json');

        $parent = GenerationHarness::run(json_decode($model, false, 512, \JSON_THROW_ON_ERROR));
        $child  = GenerationHarness::runWithGenerator(json_decode($model, false, 512, \JSON_THROW_ON_ERROR), self::LEDGER);

        $this->assertSame($parent->paths(), $child->paths());

        foreach ($parent as $path => $contents) {
            $this->assertSame($contents, $child->get($path), $path);
        }
    }

    /**
     * Shipped, it is held to what an import is: only what the target publishes.
     */
    public function testTheShippedGeneratorPassesTheImportsChecks(): void
    {
        $target = new Joomla6Target(\dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/generator_templates');

        foreach (GeneratorCatalogue::shippedPackages() as $package) {
            $this->assertSame([], $package->problems($target->vocabulary(), $target->rulePrefixes()), $package->key);
        }
    }

    public function testTheShippedRuleFileIsTheCanonicalSerialisation(): void
    {
        $path = \dirname(RuleDrivenGenerator::defaultRuleFile()) . '/ledgerlang.rules.json';

        $this->assertSame(RuleSet::fromFile($path)->toJson() . "\n", (string) file_get_contents($path));
    }

    /**
     * The parent's rules run too, so the parent's language has to be in the project's ancestry as well.
     */
    public function testItAppliesOnlyWhereEveryGeneratorInTheLineApplies(): void
    {
        $ancestry = GenerationHarness::ancestry();
        $ledger   = GenerationHarness::generator(self::LEDGER);

        $this->assertTrue($ancestry->appliesTo($ledger, [$this->language('LedgerLang'), $this->language('ER1')]));
        $this->assertFalse($ancestry->appliesTo($ledger, [$this->language('ER1')]), 'an ER1 project has no posting rules');
        $this->assertFalse($ancestry->appliesTo($ledger, [$this->language('LedgerLang')]), 'the parent reads ER1');
        $this->assertFalse(
            $this->ancestryOver([])->appliesTo($this->entry('orphan', 'nobody'), [$this->language('ER1')]),
            'a line that cannot be walked applies to nothing'
        );
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function amounts(): array
    {
        return [
            'a name'                => ['amount', "\$this->value(\$data, 'amount')"],
            'a difference'          => ['amount - vat', "\$this->value(\$data, 'amount') - \$this->value(\$data, 'vat')"],
            'brackets and a number' => ['(amount-vat) * 0.5', "(\$this->value(\$data, 'amount') - \$this->value(\$data, 'vat')) * 0.5"],
            'a unary minus'         => ['-vat', "-\$this->value(\$data, 'vat')"],
            'empty'                 => ['', null],
            'a dangling operator'   => ['amount +', null],
            'two names in a row'    => ['amount vat', null],
            'an open bracket'       => ['(amount', null],
            'a closing bracket'     => ['amount)', null],
            'a PHP variable'        => ['$amount', null],
            'a call'                => ['amount; system("ls")', null],
            'a string'              => ["'amount'", null],
        ];
    }

    /**
     * Only arithmetic over names and numbers becomes PHP; anything else is refused.
     */
    #[DataProvider('amounts')]
    public function testAnAmountBecomesPhpOnlyWhenItIsArithmetic(string $expression, ?string $php): void
    {
        $this->assertSame($php, (new LedgerDerivations())->amount($expression));
    }

    public function testALineThatCannotBeReadThrowsWhenPostedAndIsReported(): void
    {
        $derivations = new LedgerDerivations();

        $rules = $derivations->postingRules((object) [
            'account_roles' => ['r0' => (object) ['key' => 'cash']],
            'posting_rules' => ['p0' => (object) [
                'event_name' => 'Odd',
                'lines'      => [
                    'l0' => (object) ['role' => 'cash', 'side' => 'debit', 'amount' => 'amount; exit'],
                    'l1' => (object) ['role' => 'nowhere', 'side' => 'credit', 'amount' => 'amount'],
                ],
            ]],
        ]);

        $this->assertSame("\$this->unreadable('Odd')", $rules[0]['lines'][0]['amount']);
        $this->assertCount(2, $derivations->warnings());
        $this->assertStringContainsString('nowhere', $derivations->warnings()[0] . $derivations->warnings()[1]);
    }

    /**
     * The generated class does what the posting rules say, and refuses what they do not.
     */
    public function testTheGeneratedPostingRulesBalance(): void
    {
        $files     = GenerationHarness::runWithGenerator($this->ledgerModel(), self::LEDGER);
        $directory = sys_get_temp_dir() . '/extengen-ledger-' . getmypid();

        if (!is_dir($directory)) {
            mkdir($directory);
        }

        foreach (['JournalLine', 'AccountRoles', 'PostingRules'] as $class) {
            $path = $directory . '/' . $class . '.php';

            file_put_contents($path, $files->get('administrator/components/com_balloonledger/src/Ledger/' . $class . '.php'));

            require_once $path;
        }

        $namespace = 'Yepr\\Component\\BalloonLedger\\Administrator\\Ledger\\';
        $rulesName = $namespace . 'PostingRules';
        $roles     = $namespace . 'AccountRoles';

        $rules = new $rulesName();
        $lines = $rules->linesFor('FlightFlown', ['amount' => 121, 'vat' => 21]);

        $this->assertSame(
            [['liability.tickets', 'debit', 121.0], ['revenue.flights', 'credit', 100.0], ['vat.output.standard', 'credit', 21.0]],
            array_map(static fn (object $line): array => [$line->role, $line->side, $line->amount], $lines)
        );

        foreach ($lines as $line) {
            $this->assertTrue($roles::has($line->role), $line->role . ' is a declared role');
        }

        $this->assertSame([], $rules->linesFor('FlightCancelledDueToWind', ['amount' => 121]));

        try {
            $rules->linesFor('ChampagneForFarmer', ['amount' => 40]);
            $this->fail('An event without a rule is refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('no posting rule', $e->getMessage());
        }

        try {
            $rules->linesFor('FlightFlown', ['amount' => 121]);
            $this->fail('A missing amount is refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('"vat"', $e->getMessage());
        }

        array_map('unlink', glob($directory . '/*.php') ?: []);
        rmdir($directory);
    }
}
