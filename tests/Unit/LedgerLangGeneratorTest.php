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
            'a difference'          => ['amount - vat', "\$this->minus(\$this->value(\$data, 'amount'), \$this->value(\$data, 'vat'))"],
            'brackets and a number' => [
                '(amount-vat) * 0.5',
                "\$this->times(\$this->minus(\$this->value(\$data, 'amount'), \$this->value(\$data, 'vat')), "
                . "\$this->number('0.5'))",
            ],
            'a unary minus'         => ['-vat', "\$this->negated(\$this->value(\$data, 'vat'))"],
            'times before minus'    => [
                'amount - amount * 9 / 109',
                "\$this->minus(\$this->value(\$data, 'amount'), "
                . "\$this->dividedBy(\$this->times(\$this->value(\$data, 'amount'), \$this->number('9')), \$this->number('109')))",
            ],
            'left to right'         => [
                'a - b - c',
                "\$this->minus(\$this->minus(\$this->value(\$data, 'a'), \$this->value(\$data, 'b')), \$this->value(\$data, 'c'))",
            ],
            'empty'                 => ['', null],
            'a dangling operator'   => ['amount +', null],
            'two names in a row'    => ['amount vat', null],
            'an open bracket'       => ['(amount', null],
            'a closing bracket'     => ['amount)', null],
            'empty brackets'        => ['()', null],
            'a dangling minus'      => ['amount * -', null],
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
        $this->assertSame("\$this->cents(\$this->value(\$data, 'amount'))", $rules[0]['lines'][1]['amount']);
        $this->assertCount(2, $derivations->warnings());
        $this->assertStringContainsString('nowhere', $derivations->warnings()[0] . $derivations->warnings()[1]);
    }

    /**
     * Generate a ledger model and load its three Ledger classes, under a namespace of its own.
     *
     * A namespace per call, because PHP cannot load a class twice and two
     * tests generate the same component.
     *
     * @return string  The namespace the classes are in, with a trailing backslash.
     */
    private function loadLedger(object $model, string $namespace): string
    {
        $model->extensions->component->manifest->company_namespace = $namespace;

        $files     = GenerationHarness::runWithGenerator($model, self::LEDGER);
        $directory = sys_get_temp_dir() . '/extengen-ledger-' . getmypid() . '-' . $namespace;

        if (!is_dir($directory)) {
            mkdir($directory);
        }

        foreach (['JournalLine', 'AccountRoles', 'PostingRules'] as $class) {
            $path = $directory . '/' . $class . '.php';

            file_put_contents($path, $files->get('administrator/components/com_balloonledger/src/Ledger/' . $class . '.php'));

            require_once $path;
        }

        array_map('unlink', glob($directory . '/*.php') ?: []);
        rmdir($directory);

        return $namespace . '\\Component\\BalloonLedger\\Administrator\\Ledger\\';
    }

    /**
     * @param  object[]  $lines
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: int}>
     */
    private function described(array $lines): array
    {
        return array_map(static fn (object $line): array => [$line->role, $line->side, $line->amount, $line->cents], $lines);
    }

    /**
     * The generated class does what the posting rules say, and refuses what they do not.
     */
    public function testTheGeneratedPostingRulesBalance(): void
    {
        $namespace = $this->loadLedger($this->ledgerModel(), 'Balanced');
        $rulesName = $namespace . 'PostingRules';
        $roles     = $namespace . 'AccountRoles';

        $rules = new $rulesName();
        $sold  = $rules->linesFor('TicketSold', ['amount' => 218, 'vat' => 18]);

        $this->assertSame(
            [
                ['bank.psp', 'debit', '218.00', 21800],
                ['liability.tickets', 'credit', '200.00', 20000],
                ['vat.output.low', 'credit', '18.00', 1800],
            ],
            $this->described($sold)
        );

        $flown = $rules->linesFor('FlightFlown', ['amount' => '200.00']);

        $this->assertSame(
            [['liability.tickets', 'debit', '200.00', 20000], ['revenue.flights', 'credit', '200.00', 20000]],
            $this->described($flown)
        );

        foreach ([...$sold, ...$flown] as $line) {
            $this->assertTrue($roles::has($line->role), $line->role . ' is a declared role');
        }

        $this->assertSame([], $rules->linesFor('FlightCancelledDueToWind', ['amount' => 200]));

        try {
            $rules->linesFor('ChampagneForFarmer', ['amount' => 40]);
            $this->fail('An event without a rule is refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('no posting rule', $e->getMessage());
        }

        try {
            $rules->linesFor('TicketSold', ['amount' => 218]);
            $this->fail('A missing amount is refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('"vat"', $e->getMessage());
        }

        try {
            $rules->linesFor('TicketSold', ['amount' => '218 euro', 'vat' => 18]);
            $this->fail('An amount that is not a number is refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('"amount"', $e->getMessage());
        }
    }

    /**
     * Money is exact: no float between the event's data and the cents: step 6.9.
     *
     * A model of its own, so each case is one posting rule: the VAT in a gross
     * amount computed by the rule, a third that cannot balance, and the sums a
     * float gets wrong.
     */
    public function testTheGeneratedArithmeticIsExact(): void
    {
        $model = $this->ledgerModel();

        $model->posting_rules = (object) [
            'posting_rules0' => (object) ['event_name' => 'GrossSale', 'lines' => (object) [
                'lines0' => (object) ['role' => 'bank.psp', 'side' => 'debit', 'amount' => 'amount'],
                'lines1' => (object) ['role' => 'revenue.flights', 'side' => 'credit', 'amount' => 'amount - amount * 9 / 109'],
                'lines2' => (object) ['role' => 'vat.output.low', 'side' => 'credit', 'amount' => 'amount * 9 / 109'],
            ]],
            'posting_rules1' => (object) ['event_name' => 'Thirds', 'lines' => (object) [
                'lines0' => (object) ['role' => 'bank.psp', 'side' => 'debit', 'amount' => 'amount'],
                'lines1' => (object) ['role' => 'revenue.flights', 'side' => 'credit', 'amount' => 'amount / 3'],
                'lines2' => (object) ['role' => 'revenue.flights', 'side' => 'credit', 'amount' => 'amount / 3'],
                'lines3' => (object) ['role' => 'revenue.flights', 'side' => 'credit', 'amount' => 'amount / 3'],
            ]],
            'posting_rules2' => (object) ['event_name' => 'Tenths', 'lines' => (object) [
                'lines0' => (object) ['role' => 'bank.psp', 'side' => 'debit', 'amount' => 'a + b'],
                'lines1' => (object) ['role' => 'revenue.flights', 'side' => 'credit', 'amount' => '0.3'],
            ]],
            'posting_rules3' => (object) ['event_name' => 'Halves', 'lines' => (object) [
                'lines0' => (object) ['role' => 'bank.psp', 'side' => 'debit', 'amount' => 'amount / 2'],
                'lines1' => (object) ['role' => 'revenue.flights', 'side' => 'credit', 'amount' => 'amount / 2'],
            ]],
            'posting_rules4' => (object) ['event_name' => 'Nothing', 'lines' => (object) [
                'lines0' => (object) ['role' => 'bank.psp', 'side' => 'debit', 'amount' => 'amount / zero'],
                'lines1' => (object) ['role' => 'revenue.flights', 'side' => 'credit', 'amount' => 'amount'],
            ]],
        ];

        $rulesName = $this->loadLedger($model, 'Exact') . 'PostingRules';
        $rules     = new $rulesName();

        // 9/109 of 100.00 is 8.2568..., so 8.26 VAT and 91.74 net: exact, then rounded, and still 100.00.
        $this->assertSame(
            [
                ['bank.psp', 'debit', '100.00', 10000],
                ['revenue.flights', 'credit', '91.74', 9174],
                ['vat.output.low', 'credit', '8.26', 826],
            ],
            $this->described($rules->linesFor('GrossSale', ['amount' => '100.00']))
        );

        // 218 gross is 200 net and 18 VAT to the cent.
        $this->assertSame(
            ['218.00', '200.00', '18.00'],
            array_column($this->described($rules->linesFor('GrossSale', ['amount' => 218])), 2)
        );

        // 0.1 + 0.2 is 0.3. As floats it is 0.30000000000000004.
        $this->assertSame(
            ['0.30', '0.30'],
            array_column($this->described($rules->linesFor('Tenths', ['a' => 0.1, 'b' => 0.2])), 2)
        );

        // Half a cent rounds away from zero, both ways.
        $this->assertSame(['0.03', '0.03'], array_column($this->described($rules->linesFor('Halves', ['amount' => '0.05'])), 2));
        $this->assertSame(['-0.03', '-0.03'], array_column($this->described($rules->linesFor('Halves', ['amount' => '-0.05'])), 2));

        // Three thirds of 1.00 are 0.99 once each is rounded: that entry does not balance, and is refused.
        try {
            $rules->linesFor('Thirds', ['amount' => 1]);
            $this->fail('An entry that is a cent out is refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('differ by 0.01', $e->getMessage());
        }

        try {
            $rules->linesFor('Nothing', ['amount' => 1, 'zero' => 0]);
            $this->fail('Dividing by zero is refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('divides by zero', $e->getMessage());
        }

        try {
            $rules->linesFor('GrossSale', ['amount' => '99999999999999999999']);
            $this->fail('An amount too large for an integer is refused, not rounded.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('too large', $e->getMessage());
        }
    }
}
