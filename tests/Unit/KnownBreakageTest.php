<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\LegacyGeneratorRunner;

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

        LegacyGeneratorRunner::run($this->model('conference'));
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
