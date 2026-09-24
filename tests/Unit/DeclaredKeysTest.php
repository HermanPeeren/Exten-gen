<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Component\Extengen\Tests\Support\ShippedLanguage;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Package\PackageReader;

/**
 * The generators read only what the language writes.
 *
 * Three defects in a row have now had the same shape. A generator reads a key
 * off the model; the forms that once wrote that key were replaced when 3.5 made
 * ER1 a generated language; the fixtures still carry it, so everything stays
 * green while a project modelled through the screens quietly generates
 * something wrong. `field_type` was the first, the subtype payload key the
 * second, and `reference_id` the third - a key **no form has ever written**,
 * which four generators used to look up the entity a reference points at.
 *
 * Each was fixed where it was found, and none of those fixes would have caught
 * the next one. This is the general statement instead: take a model, remove
 * every key the language does not declare, and the output must not move. A key
 * nothing reads disappears without consequence; a key a generator depends on
 * takes the output with it, and this fails.
 *
 * What counts as declared is read, never listed:
 *
 * - the features and concepts of the shipped package;
 * - the fields of `project_chrome.xml`, which is the Joomla half 3.2 decided is
 *   not derivable from a language;
 * - the three names `FieldKind` derives, which are not features at all - the
 *   discriminator a generated form gives an abstract concept, and the subform
 *   named for each subtype. `FieldKindTest` checks those against the shipped
 *   form, so they are declared somewhere too, just not in the manifest.
 *
 * Anything else is not part of the language and a generator has no business
 * knowing about it.
 *
 * @since  1.4.0
 */
final class DeclaredKeysTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function models(): array
    {
        return array_map(
            static fn (string $p): array => [basename($p, '.json')],
            glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: []
        );
    }

    /**
     * Every name a model is entitled to use.
     *
     * @return array<string, true>
     */
    private function declared(): array
    {
        $declared = [];

        foreach (PackageReader::fromZip(ShippedLanguage::package())->manifest()->concepts as $concept) {
            $declared[$concept['name']] = true;

            $this->assertArrayHasKey(
                'features',
                $concept,
                $concept['name'] . ' lists no features, so this would strip the whole model.'
            );

            foreach ($concept['features'] as $feature) {
                $declared[$feature['name']] = true;
            }
        }

        $chrome = simplexml_load_file(
            \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/forms/project_chrome.xml'
        );

        $this->assertNotFalse($chrome);

        foreach ($chrome->xpath('//field[@name]') ?: [] as $field) {
            $declared[(string) $field['name']] = true;
        }

        // Not features, and not guesses either: the discriminator and the
        // subform names a generated form gives an abstract concept's subtypes.
        $declared[FieldKind::DISCRIMINATOR]       = true;
        $declared[lcfirst(FieldKind::PROPERTY)]   = true;
        $declared[lcfirst(FieldKind::REFERENCE)]  = true;

        // And what the hand-written forms called those two before 3.5, which
        // every stored model older than that still uses.
        foreach (FieldKind::LEGACY as $legacy) {
            $declared[$legacy] = true;
        }

        return $declared;
    }

    /**
     * The model with every undeclared key taken out.
     *
     * Subform rows are keyed by the field name and an index - `datamodel0`,
     * `field2` - so they are structure rather than names and are kept by the
     * shape of the key rather than by being declared anywhere.
     *
     * @param   array<mixed>          $node
     * @param   array<string, true>   $declared
     * @param   array<string, true>   $dropped   Collects what came out, by reference.
     *
     * @return  array<mixed>
     */
    private function strip(array $node, array $declared, string $parentKey, array &$dropped): array
    {
        $kept = [];

        foreach ($node as $key => $value) {
            $isRow = \is_string($key)
                && $parentKey !== ''
                && preg_match('/^' . preg_quote($parentKey, '/') . '\d+$/', $key) === 1;

            if (!$isRow && \is_string($key) && !isset($declared[$key])) {
                $dropped[$key] = true;

                continue;
            }

            $kept[$key] = \is_array($value)
                ? $this->strip($value, $declared, $isRow ? $parentKey : (string) $key, $dropped)
                : $value;
        }

        return $kept;
    }

    /**
     * @param  array<mixed>  $model
     */
    private function generate(array $model): FileCollection
    {
        return GenerationHarness::run(
            json_decode((string) json_encode($model, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array<mixed>
     */
    private function model(string $name): array
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    /**
     * Stripping the undeclared keys changes nothing.
     *
     * Restore `reference_id` to any of its four old call sites and this goes
     * red on three of the four fixtures, with PHP saying `Undefined property`
     * on the way past - which is what it should have been saying all along.
     */
    #[DataProvider('models')]
    public function testGenerationReadsOnlyDeclaredKeys(string $name): void
    {
        $model   = $this->model($name);
        $dropped = [];

        $stripped = $this->strip($model, $this->declared(), '', $dropped);

        $before = $this->generate($model);
        $after  = $this->generate($stripped);

        $this->assertSame($before->paths(), $after->paths(), $name . ' generates a different file set.');

        foreach ($before->all() as $path => $contents) {
            $this->assertSame(
                $contents,
                $after->get($path),
                $name . ': ' . $path . ' depends on a key the language does not declare.'
            );
        }
    }

    /**
     * And something was actually taken out, or the above proves nothing.
     *
     * Two identical models generate identically whatever the generators do. The
     * fixtures carry five keys no form writes - `reference_id` and three other
     * `*_reference_id` aliases left over from the hand-written forms, plus
     * Joomla's `tags` - so the strip has real work to do. If a later tidy-up
     * removes them from the fixtures this fails, and rightly: the test would
     * have stopped checking anything.
     */
    public function testTheStripHasSomethingToRemove(): void
    {
        $dropped = [];

        foreach (self::models() as [$name]) {
            $this->strip($this->model($name), $this->declared(), '', $dropped);
        }

        $this->assertNotSame([], $dropped, 'Nothing was stripped, so the comparison is empty.');

        $this->assertArrayHasKey(
            'reference_id',
            $dropped,
            'The key that caused this test to exist is no longer in any fixture.'
        );
    }
}
