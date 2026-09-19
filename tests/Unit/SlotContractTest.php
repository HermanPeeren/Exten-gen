<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\CustomCode\SlotCatalogue;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;

/**
 * A slot exists in four places at once, and they have to agree.
 *
 * The catalogue names it. A template emits it. A form offers it. The generated
 * file carries its markers. Nothing at runtime compares any of those: a slot
 * the catalogue names and no template emits stores code that is written
 * nowhere, and the person who wrote it finds out by reading the output and not
 * finding it.
 *
 * So the joins are listed here, and every one of them is checked against the
 * generated output rather than against a list written beside it.
 */
final class SlotContractTest extends TestCase
{
    /** @return array<string, string[]> */
    public static function slots(): array
    {
        $cases = [];

        foreach ((new SlotCatalogue())->ids() as $id) {
            $cases[$id] = [$id];
        }

        return $cases;
    }

    /**
     * Every slot reaches a generated file.
     */
    #[DataProvider('slots')]
    public function testTheSlotAppearsInGeneratedOutput(string $id): void
    {
        $marker = '<' . SlotCatalogue::TAG . ' id="' . $id . '">';
        $found  = [];

        foreach ($this->generated() as $path => $contents) {
            if (str_contains($contents, $marker)) {
                $found[] = $path;
            }
        }

        $this->assertNotSame(
            [],
            $found,
            'No generated file has a region for "' . $id . '", so code stored against it goes nowhere.'
        );
    }

    /**
     * And reaches exactly one file per object.
     *
     * A slot in two files means one body emitted twice, which is why the site
     * templates deliberately have none: they generate a second list model with
     * the same method names.
     */
    #[DataProvider('slots')]
    public function testTheSlotIsNotEmittedTwiceInOneFile(string $id): void
    {
        $marker    = '<' . SlotCatalogue::TAG . ' id="' . $id . '">';
        $duplicate = [];

        foreach ($this->generated() as $path => $contents) {
            if (substr_count($contents, $marker) > 1) {
                $duplicate[] = $path;
            }
        }

        $this->assertSame([], $duplicate, 'Two regions with one id in: ' . implode(', ', $duplicate));
    }

    /**
     * Every region in the output is one the catalogue knows about.
     *
     * A marker a template writes by hand would be carried across regenerations
     * by the merger and yet be fillable from no form, which reads as a slot
     * that silently does not work.
     */
    public function testEveryRegionInTheOutputIsACatalogueSlot(): void
    {
        $catalogue = new SlotCatalogue();
        $unknown   = [];

        foreach ($this->generated() as $path => $contents) {
            preg_match_all('/<' . SlotCatalogue::TAG . ' id="([^"]+)">/', $contents, $matches);

            foreach ($matches[1] as $id) {
                if (!$catalogue->has($id)) {
                    $unknown[] = $path . ' : ' . $id;
                }
            }
        }

        $this->assertSame([], $unknown, 'Regions nobody can fill: ' . implode(', ', $unknown));
    }

    /**
     * Every region is closed.
     *
     * An unclosed one swallows the rest of the file on the next merge.
     */
    public function testEveryRegionIsClosed(): void
    {
        $open  = '<' . SlotCatalogue::TAG . ' id="';
        $close = '</' . SlotCatalogue::TAG . '>';

        foreach ($this->generated() as $path => $contents) {
            $this->assertSame(
                substr_count($contents, $open),
                substr_count($contents, $close),
                $path . ' has an unbalanced region.'
            );
        }
    }

    /**
     * A form offers every slot its kind of object has, and no others.
     *
     * The forms name an owner and a page type; the catalogue decides the rest.
     * This checks the naming is one the catalogue answers to, which a typo in
     * `owner="Entitiy"` would not be.
     */
    public function testEveryFormThatOffersSlotsAsksForOnesThatExist(): void
    {
        $catalogue = new SlotCatalogue();
        $root      = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/forms';
        $checked   = 0;

        foreach (glob($root . '/*.xml') ?: [] as $path) {
            $xml = simplexml_load_file($path);

            $this->assertNotFalse($xml, $path . ' is not valid XML.');

            foreach ($xml->xpath('//field[@type="Slot"]') ?: [] as $field) {
                $owner    = (string) $field['owner'];
                $pageType = (string) ($field['pagetype'] ?? '');

                $this->assertNotSame(
                    [],
                    $catalogue->for($owner, $pageType === '' ? null : $pageType),
                    basename($path) . ' offers slots for owner "' . $owner . '"'
                        . ($pageType === '' ? '' : ' and page type "' . $pageType . '"')
                        . ', and the catalogue has none.'
                );

                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked, 'No form offers a slot, so this rule is watching nothing.');
    }

    /**
     * Everything the golden models generate, once.
     *
     * @return array<string, string>
     */
    private function generated(): array
    {
        static $files = null;

        if ($files !== null) {
            return $files;
        }

        $files = [];

        foreach (glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: [] as $model) {
            $name = basename($model, '.json');

            foreach (GenerationHarness::runJson((string) file_get_contents($model))->all() as $path => $contents) {
                $files[$name . '/' . $path] = $contents;
            }
        }

        return $files;
    }
}
