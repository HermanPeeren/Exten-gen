<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;

/**
 * Things the golden files cannot hold, written down so they are not rediscovered.
 *
 * A golden file records output. It cannot record a generator that throws before
 * producing any, and it cannot record a file written twice — the second write
 * simply wins, and the file looks ordinary afterwards.
 *
 * These tests pass while the behaviour is present. Fixing one turns its test
 * red, which is the reminder to capture the newly correct output and delete it.
 * That is how the front-end crash left: recorded here at 1.2, fixed at 1.5, and
 * the test went with it.
 */
final class KnownBreakageTest extends TestCase
{
    /**
     * A page listed twice in a section is generated twice, silently.
     *
     * `eventschedule` lists `Tracks` twice among its back-end pages, and does
     * not list `Track` at all. The generator loops over the *references* in a
     * section rather than over the pages, so it produces the whole Tracks
     * quartet twice and the Track details page never.
     *
     * Both halves were invisible until 1.4. The generator called
     * `fopen(..., 'w')` for every file, and a second write to the same path is
     * just a write; nothing counted references, so nothing noticed the missing
     * page either. Generating into a collection that refuses a path it already
     * holds made both visible at once.
     *
     * What it costs is small and real: the four MVC files are overwritten with
     * identical content, and the manifest gets a **duplicated submenu entry**
     * for Tracks. Measured rather than assumed — generating the same model with
     * the second reference removed changes exactly one file.
     *
     * Deliberately not fixed here. The model says something contradictory, so
     * the repair belongs in the model rather than in a generator that quietly
     * tidies up after it — and which of those to do is a decision about stored
     * data, not about this code. A validator rule refusing a section that names
     * a page twice is the candidate.
     */
    public function testAPageListedTwiceIsStillGeneratedTwice(): void
    {
        $collisions = GenerationHarness::collisions($this->model('eventschedule'));

        $this->assertSame(
            [
                'administrator/components/com_eventschedule/src/Controller/TracksController.php' => 2,
                'administrator/components/com_eventschedule/src/Model/TracksModel.php'           => 2,
                'administrator/components/com_eventschedule/src/View/Tracks/HtmlView.php'        => 2,
                'administrator/components/com_eventschedule/tmpl/tracks/default.php'             => 2,
            ],
            $collisions,
            'The whole MVC set for one page is generated twice.'
        );
    }

    /**
     * The same data problem, in a different repeating group.
     *
     * `conference` lists `en-GB` twice among its languages, so each en-GB file
     * is written twice. Found by the counter rather than by anybody looking:
     * this one had never been suspected, and it is the reason the rule worth
     * having is about duplicates in a repeating group generally, not about page
     * references in particular.
     */
    public function testADuplicatedLanguageWritesTheSameFilesTwice(): void
    {
        $this->assertSame(
            [
                'administrator/components/com_conference/language/en-GB/com_conference.ini'     => 2,
                'administrator/components/com_conference/language/en-GB/com_conference.sys.ini' => 2,
                'components/com_conference/language/en-GB/com_conference.ini'                   => 2,
            ],
            GenerationHarness::collisions($this->model('conference'))
        );
    }

    /**
     * And a model without a duplicated entry anywhere produces none, so the
     * counter is reporting something real rather than firing on every run.
     */
    public function testACleanModelHasNoCollisions(): void
    {
        $this->assertSame([], GenerationHarness::collisions($this->model('balloonplanning')));
    }

    /** A model from the golden set. */
    private function model(string $name): object
    {
        $path = \dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json';

        return json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    }
}
