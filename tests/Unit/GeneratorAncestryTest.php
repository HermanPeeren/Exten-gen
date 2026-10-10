<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Imported\GeneratorPackage;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorAncestry;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorEntry;
use Yepr\Gen\Core\Rule\Rule;
use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

/**
 * A generator that extends another: step 5.9.
 *
 * The same relation as language ancestry, one level up. A child runs every
 * rule of its parent and adds its own; a rule with a parent's id takes that
 * rule's place; there is no way to remove one. The walk refuses a missing
 * parent, a cycle and a parent for another target, as `Ancestry` refuses a
 * missing or cyclic language.
 *
 * @since  1.6.0
 */
final class GeneratorAncestryTest extends TestCase
{
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

    /**
     * @return array<string, mixed>
     */
    private function rule(string $id, string $target = 'x.txt'): array
    {
        return ['id' => $id, 'for' => 'root', 'template' => 'LICENSE', 'target' => $target];
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

    public function testAChildRuleWithAParentsIdTakesItsPlace(): void
    {
        $parent = RuleSet::fromArray([$this->rule('a'), $this->rule('b'), $this->rule('c')]);
        $child  = RuleSet::fromArray([$this->rule('d'), $this->rule('b', 'other.txt')]);

        $composed = GeneratorAncestry::compose($parent, $child);

        $this->assertSame(['a', 'b', 'c', 'd'], $this->ids($composed));
        $this->assertSame('other.txt', iterator_to_array($composed)[1]->target);
        $this->assertSame(['b'], GeneratorAncestry::overrides($parent, $child));
    }

    public function testAGrandchildRunsAllThree(): void
    {
        $root  = $this->entry('root', '', [$this->rule('a')]);
        $child = $this->entry('child', 'root', [$this->rule('b')]);
        $grand = $this->entry('grand', 'child', [$this->rule('c'), $this->rule('a', 'mine.txt')]);

        $ancestry = $this->ancestryOver([$root, $child, $grand]);

        $this->assertSame(['child', 'root'], array_map(static fn (GeneratorEntry $e): string => $e->id, $ancestry->of($grand)));
        $this->assertSame(['a', 'b', 'c'], $this->ids($ancestry->rules($grand)));
    }

    public function testAMissingParentIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('which this site has not got');

        $this->ancestryOver([])->of($this->entry('orphan', 'nobody'));
    }

    public function testACycleIsRefused(): void
    {
        $a = $this->entry('a', 'b', [$this->rule('x')]);
        $b = $this->entry('b', 'a', [$this->rule('y')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('extends itself');

        $this->ancestryOver([$a, $b])->of($a);
    }

    public function testAParentForAnotherTargetIsRefused(): void
    {
        $wordpress = $this->entry('wp', '', [$this->rule('x')], 'wordpress');
        $child     = $this->entry('child', 'wp', [$this->rule('y')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('which is for wordpress');

        $this->ancestryOver([$wordpress, $child])->of($child);
    }

    public function testAnImportedGeneratorKeepsWhatItExtendsInTheManifestColumn(): void
    {
        $entry = GeneratorEntry::fromRow((object) [
            'gen_key'  => 'mine',
            'target'   => 'joomla6',
            'rules'    => '[]',
            'manifest' => '{"groups":[],"extends":"joomla6-ledgerlang"}',
        ], 'Joomla component');

        $old = GeneratorEntry::fromRow((object) ['gen_key' => 'old', 'manifest' => '{"groups":[]}'], '');

        $this->assertSame('joomla6-ledgerlang', $entry->extends);
        $this->assertSame('', $old->extends, 'a row from before 5.9 extends nothing');
    }

    public function testAFormatOnePackageStillReadsAndExtendsNothing(): void
    {
        $package = GeneratorPackage::fromManifest(
            ['format' => 1, 'name' => 'Old', 'target' => 'joomla6'],
            RuleSet::fromArray([$this->rule('a')])->toJson()
        );

        $this->assertSame('', $package->extends);
    }

    /**
     * The parent's rules run too, so the parent's language has to be in the project's ancestry as well.
     */
    public function testAChildAppliesOnlyWhereItsParentDoesToo(): void
    {
        $parent = $this->entry('parent', '', [$this->rule('a')]);
        $child  = $this->entry('child', 'parent', [$this->rule('b')], 'joomla6', 'DerivedER');

        $ancestry = $this->ancestryOver([$parent, $child]);

        $this->assertTrue($ancestry->appliesTo($child, [$this->language('DerivedER'), $this->language('ER1')]));
        $this->assertFalse($ancestry->appliesTo($child, [$this->language('ER1')]), 'not its own language');
        $this->assertFalse($ancestry->appliesTo($child, [$this->language('DerivedER')]), 'not its parent\'s');
        $this->assertFalse(
            $this->ancestryOver([])->appliesTo($child, [$this->language('DerivedER'), $this->language('ER1')]),
            'a line that cannot be walked applies to nothing'
        );
    }
}
