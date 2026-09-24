<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\CustomCode\SlotCatalogue;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Gen\Core\Output\ProtectedRegionMerger;

/**
 * Code written in the model comes out in the generated file.
 *
 * The whole chain, through the real generators and the real templates: a stored
 * model with custom code in it, generated, and the file read back. Everything
 * else about slots is checked in pieces; this is the one that would notice if
 * the pieces stopped meeting.
 *
 * The model is a golden fixture with custom code added, so it is a shape that
 * really occurs rather than one written to make this pass.
 */
final class CustomCodeGenerationTest extends TestCase
{
    private const CHECK = "\t\tif (\$this->capacity < 1) {\n"
        . "\t\t\t\$this->setError('A balloon carries at least one passenger.');\n\n"
        . "\t\t\treturn false;\n"
        . "\t\t}";

    private const METHOD = "\tpublic function isGrounded(): bool\n"
        . "\t{\n"
        . "\t\treturn \$this->published === 0;\n"
        . "\t}";

    public function testCodeStoredAgainstAnEntityIsInThatEntitysTable(): void
    {
        $files = $this->generate();
        $table = $files['administrator/components/com_balloonplanning/src/Table/BalloonTable.php'];

        $this->assertStringContainsString('A balloon carries at least one passenger.', $table);
        $this->assertStringContainsString('public function isGrounded(): bool', $table);
    }

    /**
     * It lands where the slot said it would, not merely somewhere in the file.
     */
    public function testTheCheckLandsInsideCheckAndTheMethodInTheClassBody(): void
    {
        $files = $this->generate();
        $table = $files['administrator/components/com_balloonplanning/src/Table/BalloonTable.php'];

        $check = $this->between($table, 'public function check()', 'public function getTypeAlias()');

        $this->assertStringContainsString('A balloon carries at least one passenger.', $check);
        $this->assertStringNotContainsString('isGrounded', $check);

        // The extra method is after the last method and before the class ends.
        $tail = substr($table, (int) strrpos($table, 'public function delete('));

        $this->assertStringContainsString('public function isGrounded(): bool', $tail);
    }

    /**
     * Another entity's table does not get it.
     */
    public function testAnotherEntityIsUntouched(): void
    {
        $files = $this->generate();

        foreach ($files as $path => $contents) {
            if (str_contains($path, 'Table/') && !str_contains($path, 'BalloonTable')) {
                $this->assertStringNotContainsString('isGrounded', $contents, $path);
            }
        }
    }

    /**
     * What is emitted is what the merger reads back.
     *
     * If the region the generator writes is not one the merger recognises, the
     * safety net is not there and nobody finds out until a regeneration has
     * already eaten an edit.
     */
    public function testTheRegionsInGeneratedOutputAreOnesTheMergerFinds(): void
    {
        $files = $this->generate();
        $table = $files['administrator/components/com_balloonplanning/src/Table/BalloonTable.php'];

        $regions = (new ProtectedRegionMerger(SlotCatalogue::TAG))->extract($table);

        $this->assertArrayHasKey('table.check', $regions);
        $this->assertArrayHasKey('table.methods', $regions);
        $this->assertStringContainsString('A balloon carries at least one passenger.', $regions['table.check']);
    }

    /**
     * A regeneration of the same model produces the same file.
     *
     * The merger runs over the previous output, so a region that came from the
     * model has to survive a round trip through it unchanged. Anything else and
     * the file would drift a little on every run.
     */
    public function testRegeneratingOverTheLastRunChangesNothing(): void
    {
        $files = $this->generate();
        $table = $files['administrator/components/com_balloonplanning/src/Table/BalloonTable.php'];

        $merged = (new ProtectedRegionMerger(SlotCatalogue::TAG))->merge($table, $table);

        $this->assertSame($table, $merged);
    }

    /**
     * The generated PHP is still PHP.
     *
     * Custom code is pasted in verbatim, so this is the one thing a slot can
     * break that nothing else would catch until somebody installed the result.
     */
    public function testTheGeneratedFileStillParses(): void
    {
        $files = $this->generate();
        $table = $files['administrator/components/com_balloonplanning/src/Table/BalloonTable.php'];

        $path = tempnam(sys_get_temp_dir(), 'extengen') . '.php';

        file_put_contents($path, $table);

        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

        unlink($path);

        $this->assertSame(0, $status, implode("\n", $output));
    }

    /**
     * A front-end layout that renders the page itself.
     *
     * `return;` is the whole point of where this slot sits: a `tmpl` file is
     * included, so returning from it leaves the generated layout below
     * unexecuted. That is how a model asks for a custom front-end template
     * without needing a way to say "replace this".
     */
    private const LAYOUT = <<<'PHP'
        echo '<h1>' . count($this->items) . ' flights</h1>';
        return;
        PHP;

    /**
     * And a condition a visitor's list should have that an editor's should not.
     */
    private const SITE_QUERY = <<<'PHP'
        $query->where($db->quoteName('flight.published') . ' = 1');
        PHP;

    /**
     * Front-end code lands in the front-end files, and nowhere else.
     *
     * The five `site.` slots exist because the site templates generate a second
     * list model and a second details model with the same method names as the
     * administrator ones. A shared slot would have put one body in two files
     * without saying so - a visitor's list quietly gaining an editor's
     * conditions, or the other way round, which is the sort of thing that is
     * found by somebody seeing rows they should not.
     *
     * So this checks both halves: the code is in the site model, and it is not
     * in the administrator one.
     */
    public function testFrontEndCodeLandsInTheFrontEndFilesOnly(): void
    {
        $files = $this->generate();

        $site  = $files['components/com_balloonplanning/src/Model/FlightsModel.php'];
        $admin = $files['administrator/components/com_balloonplanning/src/Model/FlightsModel.php'];

        $this->assertStringContainsString(self::SITE_QUERY, $site);
        $this->assertStringNotContainsString(
            self::SITE_QUERY,
            $admin,
            "A visitor's condition reached the administrator's list."
        );
    }

    /**
     * A layout slot puts the body above the layout it can replace.
     *
     * Above, because the body may `return;` - and a return that came after the
     * generated markup would have rendered it first, which is the one thing a
     * custom template must not do.
     */
    public function testALayoutSlotSitsAboveTheLayoutItCanReplace(): void
    {
        $layout = $this->generate()['components/com_balloonplanning/tmpl/flights/default.php'];

        $this->assertStringContainsString(self::LAYOUT, $layout);

        $this->assertLessThan(
            strpos($layout, '<form action='),
            strpos($layout, self::LAYOUT),
            'The custom layout runs after the generated one, so returning from it renders both.'
        );

        // And the imports are above it, because a body that calls HTMLHelper
        // should read as though it may. Asserted present first: strpos() of a
        // needle that is not there is false, which compares as 0, which would
        // have made "the imports come first" true by their absence.
        $this->assertStringContainsString('use \Joomla\CMS\HTML\HTMLHelper;', $layout);

        $this->assertLessThan(
            strpos($layout, self::LAYOUT),
            strpos($layout, 'use \\Joomla\\CMS\\HTML\\HTMLHelper;'),
            'The custom layout sits above the imports.'
        );
    }

    /**
     * Generate the balloonplanning model with custom code added to one entity.
     *
     * @return array<string, string>
     */
    private function generate(): array
    {
        static $files = null;

        if ($files !== null) {
            return $files;
        }

        $model = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/balloonplanning.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($model->datamodel as $entity) {
            if (($entity->entity_name ?? '') === 'Balloon') {
                $entity->customcode = (object) [
                    'customcode0' => (object) ['slot' => 'table.check', 'code' => self::CHECK],
                    'customcode1' => (object) ['slot' => 'table.methods', 'code' => self::METHOD],
                ];
            }
        }

        foreach ($model->pages as $page) {
            if (($page->page_name ?? '') === 'Flights') {
                $page->customcode = (object) [
                    'customcode0' => (object) ['slot' => 'site.index.layout', 'code' => self::LAYOUT],
                    'customcode1' => (object) ['slot' => 'site.listmodel.query', 'code' => self::SITE_QUERY],
                ];
            }
        }

        return $files = GenerationHarness::run($model)->all();
    }

    private function between(string $haystack, string $from, string $to): string
    {
        $start = strpos($haystack, $from);
        $end   = strpos($haystack, $to);

        $this->assertNotFalse($start, 'Cannot find ' . $from);
        $this->assertNotFalse($end, 'Cannot find ' . $to);

        return substr($haystack, (int) $start, (int) $end - (int) $start);
    }
}
