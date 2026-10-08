<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * No browser spec installs an extension from outside the web server.
 *
 * `generated-front-end.cy.js` installed the generated component through
 * Joomla's CLI, and in CI it failed now and then with a bare 500 on the first
 * visit. Joomla clears the opcode cache for the files an install writes - the
 * namespace map among them - in the process that writes them. The CLI is
 * another process, so the server went on running the old map, without the new
 * component in it, until its own timestamp check came round.
 *
 * The spec installs with Exten-gen's own Install button now, in the server,
 * as a real site would. This keeps the next spec from doing it the quick way.
 * The tool is still there for a person, who is never fast enough to notice.
 *
 * @since  1.3.5
 */
final class BrowserSpecInstallsTest extends TestCase
{
    /**
     * What installing from the command line looks like inside a spec.
     */
    private const CLI_INSTALLS = [
        'tools/install-generated.php',
        'extension:install',
        'tools/install-local.php',
    ];

    public function testNoSpecInstallsThroughTheCommandLine(): void
    {
        $specs = glob(\dirname(__DIR__) . '/cypress/e2e/*.cy.js') ?: [];
        $found = [];

        $this->assertNotEmpty($specs, 'the specs are where this looks');

        foreach ($specs as $spec) {
            $source = (string) file_get_contents($spec);

            foreach (self::CLI_INSTALLS as $install) {
                if (preg_match('/cy\.exec\([^)]*' . preg_quote($install, '/') . '/', $source)) {
                    $found[] = basename($spec) . ': ' . $install;
                }
            }
        }

        $this->assertSame([], $found, 'install through the site - the Install button - so the server clears its own cache');
    }
}
