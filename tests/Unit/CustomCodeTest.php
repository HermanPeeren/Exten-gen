<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\CustomCode\CustomCode;
use Yepr\Component\Extengen\Administrator\CustomCode\SlotCatalogue;
use Yepr\Gen\Core\Output\ProtectedRegionMerger;

/**
 * Code written in the model reaches the generated file, and survives.
 *
 * The two halves are tested together here because neither means anything
 * alone: a region the generator emits has to be one the merger recognises, and
 * the only way to know that is to emit one and read it back.
 */
final class CustomCodeTest extends TestCase
{
    public function testAnObjectWithNoCustomCodeStillGetsItsSlots(): void
    {
        // Empty regions are not waste. They are where the merger puts back an
        // edit made in the output, and they tell a reader of generated code
        // where writing is allowed.
        $regions = (new CustomCode())->regions(null, 'Entity');

        $this->assertSame(['table.check', 'table.methods'], array_keys($regions));

        foreach ($regions as $id => $region) {
            $this->assertStringContainsString('<extengen id="' . $id . '">', $region);
            $this->assertStringContainsString('</extengen>', $region);
        }
    }

    public function testCodeFromTheModelIsInsideTheRegion(): void
    {
        $regions = (new CustomCode())->regions(
            $this->node([['slot' => 'table.check', 'code' => '        $this->name = trim($this->name);']]),
            'Entity'
        );

        $this->assertStringContainsString('$this->name = trim($this->name);', $regions['table.check']);
        $this->assertStringNotContainsString('$this->name', $regions['table.methods']);
    }

    /**
     * The round trip, which is the whole claim.
     */
    public function testWhatIsEmittedIsWhatTheMergerReadsBack(): void
    {
        $code    = '        $this->name = trim($this->name);';
        $regions = (new CustomCode())->regions(
            $this->node([['slot' => 'table.check', 'code' => $code]]),
            'Entity'
        );

        $file = "<?php\nclass Foo\n{\n" . $regions['table.check'] . "\n}\n";

        $extracted = (new ProtectedRegionMerger(SlotCatalogue::TAG))->extract($file);

        $this->assertArrayHasKey('table.check', $extracted);
        $this->assertSame($code, rtrim($extracted['table.check'], "\r\n"));
    }

    /**
     * An edit made in the generated file comes back on the next run.
     *
     * This is the safety net rather than the mechanism, and it is what makes
     * regenerating over somebody's work safe enough to do without asking.
     */
    public function testAnEditInTheOutputSurvivesRegeneration(): void
    {
        $merger = new ProtectedRegionMerger(SlotCatalogue::TAG);

        // What a previous run left on disk, with a line somebody added by hand
        // inside the region. Written out rather than derived, so that this
        // cannot pass because the two sides share a mistake.
        $onDisk = "<?php\n"
            . '        // <extengen id="table.check">' . "\n"
            . '        $this->checked = true;' . "\n"
            . '        // </extengen>' . "\n";

        // What this run generates: the same region, empty, because the model
        // says nothing about this slot.
        $generated = "<?php\n" . (new CustomCode())->regions(null, 'Entity')['table.check'] . "\n";

        $this->assertStringNotContainsString('$this->checked', $generated);

        $merged = $merger->merge($onDisk, $generated);

        $this->assertStringContainsString('$this->checked = true;', $merged);
        $this->assertSame([], $merger->orphanedRegions());
    }

    /**
     * The model wins over the file when both have something to say.
     *
     * The model is the source; an edit in the output is a rescue. If the two
     * disagree, believing the output would mean the model silently stopped
     * describing the extension.
     */
    public function testCodeInTheModelIsWhatEndsUpInTheFile(): void
    {
        $regions = (new CustomCode())->regions(
            $this->node([['slot' => 'table.check', 'code' => '        // from the model']]),
            'Entity'
        );

        $this->assertStringContainsString('// from the model', $regions['table.check']);
    }

    public function testAPageOnlyGetsTheSlotsItsKindOfPageHas(): void
    {
        $custom = new CustomCode();

        $index   = array_keys($custom->regions(null, 'Page', 'indexpage'));
        $details = array_keys($custom->regions(null, 'Page', 'detailspage'));

        // The property, not the list. Naming the slots here meant that adding
        // the five front-end ones failed this test for being new rather than
        // for being wrong - and a test that has to be edited every time the
        // thing it guards grows is one somebody eventually edits without
        // reading.
        $this->assertNotSame([], $index);
        $this->assertNotSame([], $details);

        $this->assertSame(
            [],
            array_intersect($index, $details),
            'A slot offered to both kinds of page cannot say which file it ends up in.'
        );

        // And both halves are real: an index page has a list query to extend
        // and a details page does not, which is the distinction the filtering
        // exists to make.
        $this->assertContains('listmodel.query', $index);
        $this->assertContains('detailsmodel.methods', $details);
    }

    public function testAnEmptyBodyIsTreatedAsNoCode(): void
    {
        $regions = (new CustomCode())->regions(
            $this->node([['slot' => 'table.check', 'code' => "   \n  "]]),
            'Entity'
        );

        $this->assertSame(
            (new CustomCode())->regions(null, 'Entity')['table.check'],
            $regions['table.check']
        );
    }

    /**
     * Code stored against a slot that no longer exists is reported.
     *
     * Nothing else would notice: generation would produce a file without it,
     * and the code would sit in the model being nowhere.
     */
    public function testCodeForASlotThatDoesNotExistIsReported(): void
    {
        $node = $this->node([
            ['slot' => 'table.check', 'code' => 'kept'],
            ['slot' => 'table.thisWasRemoved', 'code' => 'orphaned'],
        ]);

        $custom = new CustomCode();

        $this->assertSame(['table.thisWasRemoved'], $custom->unknownSlots($node));
        $this->assertSame(['table.check'], array_keys($custom->bodies($node)));
    }

    public function testEverySlotTheCatalogueNamesHasALabelAndAnExplanation(): void
    {
        $catalogue = new SlotCatalogue();

        foreach ($catalogue->ids() as $id) {
            $slot = $catalogue->get($id);

            $this->assertNotSame('', $slot['label'], $id . ' has no label');
            $this->assertNotSame('', $slot['description'], $id . ' does not say what is in scope');
            $this->assertContains($slot['owner'], ['Entity', 'Page'], $id . ' belongs to nothing that exists');
        }
    }

    public function testAskingForASlotThatIsNotThereSaysSo(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new SlotCatalogue())->get('no.such.slot');
    }

    /** @param  list<array{slot: string, code: string}>  $entries */
    private function node(array $entries): object
    {
        $customcode = [];

        foreach ($entries as $index => $entry) {
            $customcode['customcode' . $index] = $entry;
        }

        return json_decode(
            (string) json_encode(['customcode' => $customcode]),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
