<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;

/**
 * Bugs the baseline cannot hold, written down so they are not rediscovered.
 *
 * A golden file records output. It cannot record a generator that throws before
 * producing any, and that is exactly what happens to a model whose front-end
 * section contains a details page — so it is recorded here instead.
 *
 * These tests pass while the bug is present. Fixing it turns them red, which is
 * the point: the failure is the reminder to capture the newly working output and
 * delete the test. Step 1.5 is where that happens.
 */
final class KnownBreakageTest extends TestCase
{
    private function model(string $name): object
    {
        $path = \dirname(__DIR__) . '/Fixtures/known-breakage/' . $name . '.json';

        return json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * SiteMVC asks for a template the site side does not have.
     *
     * `SiteMVC::generate()` renders `tmpl/details/edit.php.twig`, which exists
     * for the administrator and not for the site, where the equivalent is
     * `default.php.twig`. It is a copy-paste from `AdminMVC`, and it means front
     * end generation has never worked for a details page: every committed
     * example under `generated/` has index pages only.
     *
     * The fix is one template name, but it is not made here — 1.2 changes no
     * code, so that the port at 1.4 is measured against today's behaviour rather
     * than a version of it nobody has run.
     */
    public function testAFrontEndDetailsPageStillCrashesGeneration(): void
    {
        $this->expectException(\Twig\Error\LoaderError::class);
        $this->expectExceptionMessageMatches('/Unable to find template "edit\.php\.twig"/');

        GenerationHarness::run($this->model('conference'));
    }

    /**
     * A page listed twice in a section is generated twice, silently.
     *
     * `eventschedule` lists `Tracks` twice among its back-end pages, and does
     * not list `Track` at all. The generator loops over the *references* in the
     * section rather than over the pages, so it produced the whole Tracks
     * quartet twice and the Track details page never.
     *
     * Both halves of that were invisible. The generator called
     * `fopen(..., 'w')` for every file, and a second write to the same path is
     * just a write; nothing counted references, so nothing noticed the missing
     * page either. It surfaced only when generation started going into a
     * collection that refuses a path it already holds.
     *
     * So this is a model that has drifted rather than a generator that is
     * wrong - but a generator that cannot tell the difference is worth fixing
     * too. Step 1.5: the validator should refuse a section that names a page
     * twice, which turns a silent overwrite into a sentence.
     *
     * Until then the last write wins, exactly as before, so that moving the
     * writing changed no output.
     */
    public function testAPageListedTwiceIsStillGeneratedTwice(): void
    {
        $collisions = GenerationHarness::collisions($this->goldenModel('eventschedule'));

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

    public function testAModelWithoutCollidingPageNamesHasNone(): void
    {
        $this->assertSame([], GenerationHarness::collisions($this->goldenModel('balloonplanning')));
    }

    /** A model from the golden set, which generates cleanly. */
    private function goldenModel(string $name): object
    {
        $path = \dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json';

        return json_decode((string) file_get_contents($path), false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * And the template really is missing, rather than merely unfound.
     *
     * Without this, the test above would keep passing if the loader broke for
     * some entirely different reason.
     */
    public function testTheSiteSideHasNoEditTemplate(): void
    {
        $siteTemplates = \dirname(__DIR__, 2)
            . '/src/administrator/components/com_extengen/generator_templates/Joomla4'
            . '/component/components/com_componentname/tmpl/details';

        $this->assertFileExists($siteTemplates . '/default.php.twig');
        $this->assertFileDoesNotExist($siteTemplates . '/edit.php.twig');
    }
}
