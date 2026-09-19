<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The manifest describes the package, and the package contains what it says.
 *
 * The gap this exists to prevent was real and invisible: `generator_templates`
 * was not in the `<files>` inventory, so a proper install shipped a generator
 * with no templates for it. Nobody hit it because development ran off symlinks
 * into a working copy, where every file is present whatever the manifest says.
 *
 * These tests read the manifest rather than a list written beside it, so adding
 * a folder to the component without listing it fails here.
 */
final class PackageTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function manifest(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/src/extengen.xml');

        $this->assertNotFalse($xml, 'The package manifest is not valid XML.');

        return $xml;
    }

    public function testEveryFileTheManifestClaimsIsThere(): void
    {
        $manifest = $this->manifest();
        $base     = $this->root() . '/src/administrator/components/com_extengen/';
        $missing  = [];

        foreach ($manifest->administration->files->filename as $file) {
            if (!is_file($base . (string) $file)) {
                $missing[] = (string) $file;
            }
        }

        foreach ($manifest->administration->files->folder as $folder) {
            if (!is_dir($base . (string) $folder)) {
                $missing[] = (string) $folder . '/';
            }
        }

        $this->assertSame([], $missing, 'The manifest claims these and they are not there: ' . implode(', ', $missing));
    }

    /**
     * The other direction, which is the one that actually bit.
     *
     * A folder that exists and is not listed is shipped by nobody: it works in
     * development, where the component is a symlink to the working copy, and is
     * missing the moment somebody installs the package.
     */
    public function testEveryFolderInTheComponentIsListed(): void
    {
        $base = $this->root() . '/src/administrator/components/com_extengen';

        $listed = [];

        foreach ($this->manifest()->administration->files->folder as $folder) {
            $listed[] = (string) $folder;
        }

        // Products rather than sources: neither belongs in a package.
        $products = ['generated', 'compilation_cache'];

        $unlisted = [];

        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($base . '/' . $entry)) {
                continue;
            }

            if (!\in_array($entry, $listed, true) && !\in_array($entry, $products, true)) {
                $unlisted[] = $entry;
            }
        }

        $this->assertSame([], $unlisted, 'These folders exist but no manifest lists them: ' . implode(', ', $unlisted));
    }

    /**
     * There is one manifest.
     *
     * There used to be two, kept in step by hand, and the second was listed in
     * the first's own `<files>`. It was never needed: `Installer::copyManifest()`
     * puts the manifest into the component folder during installation, which is
     * why a core component like com_content ships exactly one.
     */
    public function testThereIsOnlyOneManifest(): void
    {
        $this->assertFileExists($this->root() . '/src/extengen.xml');

        $this->assertFileDoesNotExist(
            $this->root() . '/src/administrator/components/com_extengen/extengen.xml',
            'Joomla copies the manifest into the component folder on install; a second one in the repository is a second place to be wrong.'
        );

        $this->assertStringNotContainsString(
            '<filename>extengen.xml</filename>',
            (string) file_get_contents($this->root() . '/src/extengen.xml')
        );
    }

    /**
     * The install script and the manifest agree about the environment.
     *
     * The script used to insist on Joomla 4.0 while the output targeted 6, so
     * it would have let the component onto a site it could not run on.
     */
    public function testTheInstallScriptRequiresJoomlaSix(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        $this->assertMatchesRegularExpression('/minimumJoomlaVersion\s*=\s*\x276\./', $script);
        $this->assertMatchesRegularExpression('/minimumPHPVersion\s*=\s*\x278\.3\x27/', $script);
    }

    /**
     * And it knows which library release it needs, because the build reads that
     * number to decide what to bundle.
     */
    public function testTheInstallScriptNamesTheLibraryItNeeds(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        $this->assertMatchesRegularExpression("/LIBRARY_MINIMUM\s*=\s*'[0-9]+\.[0-9]+\.[0-9]+'/", $script);
        $this->assertStringContainsString("LIBRARY = 'yepr_gen'", $script);
    }
}
