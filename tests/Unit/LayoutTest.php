<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The repository mirrors a Joomla installation, and stays that way.
 *
 * `src/` is the root of the installable package: every file sits at the path it
 * will occupy on a site. The manifest's `folder=` attributes then name real
 * directories rather than translating between two layouts, and adding a part
 * that does not exist yet - site code, an API application - is a folder in the
 * obvious place rather than a decision.
 *
 * This replaced `src/com_extengen/administrator/...`, where the package root was
 * a directory named after the component and every path had to be read twice.
 * The check exists because that is exactly the kind of thing that creeps back
 * one merge at a time.
 */
final class LayoutTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function testTheSourceTreeMirrorsAJoomlaInstallation(): void
    {
        foreach (['src/administrator/components/com_extengen', 'src/media/com_extengen'] as $path) {
            $this->assertDirectoryExists($this->root() . '/' . $path);
        }
    }

    public function testThePackageManifestSitsAtTheRootOfTheSourceTree(): void
    {
        // The installer reads this one. The copy inside the component folder is
        // what a site keeps afterwards; both exist, and they have to agree.
        $this->assertFileExists($this->root() . '/src/extengen.xml');
    }

    public function testTheOldNestedLayoutIsGone(): void
    {
        $this->assertDirectoryDoesNotExist(
            $this->root() . '/src/com_extengen',
            'The package root is src/ itself; a directory named after the component means the flattening was undone.'
        );
    }

    public function testBothManifestsDeclareTheSameVersion(): void
    {
        $package   = simplexml_load_file($this->root() . '/src/extengen.xml');
        $installed = simplexml_load_file(
            $this->root() . '/src/administrator/components/com_extengen/extengen.xml'
        );

        $this->assertNotFalse($package);
        $this->assertNotFalse($installed);

        // Two copies of a manifest are two places for a version to be wrong.
        // Until the build generates one from the other - step 1.6 - the only
        // defence is saying so here.
        $this->assertSame(
            trim((string) $package->version),
            trim((string) $installed->version),
            'The package manifest and the installed copy disagree about the version.'
        );
    }
}
