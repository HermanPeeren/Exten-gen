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
     * The media folder ships everything in it, both directions.
     *
     * `<media>` had `<folder>js</folder>` and nothing else, so
     * `joomla.asset.json` beside it was never installed - and the first thing
     * that asked for an asset by name, `useScript('com_extengen.reference')`,
     * got "there is no such asset in the registry" and a 500 on the project
     * edit form. The `<files>` rules above could not see it: they read the
     * administration section, and this is a different one.
     */
    public function testTheMediaFolderShipsEverythingInIt(): void
    {
        $manifest = $this->manifest();
        $base     = $this->root() . '/src/media/com_extengen/';

        $listedFiles   = [];
        $listedFolders = [];

        foreach ($manifest->media->filename ?? [] as $file) {
            $listedFiles[] = (string) $file;
        }

        foreach ($manifest->media->folder ?? [] as $folder) {
            $listedFolders[] = (string) $folder;
        }

        $missing  = [];
        $unlisted = [];

        foreach ($listedFiles as $file) {
            if (!is_file($base . $file)) {
                $missing[] = $file;
            }
        }

        foreach ($listedFolders as $folder) {
            if (!is_dir($base . $folder)) {
                $missing[] = $folder . '/';
            }
        }

        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $known = is_dir($base . $entry) ? $listedFolders : $listedFiles;

            if (!\in_array($entry, $known, true)) {
                $unlisted[] = $entry;
            }
        }

        $this->assertSame([], $missing, 'The media section claims these: ' . implode(', ', $missing));
        $this->assertSame([], $unlisted, 'These are in media and no manifest ships them: ' . implode(', ', $unlisted));
    }

    /**
     * Every asset the layouts ask for by name is one somebody declares.
     *
     * `useScript(...)` is a promise about a file, and nothing at runtime checks
     * it: an asset Joomla cannot resolve raises nothing at all and simply never
     * reaches the page, so the script is missing and the form looks fine until
     * somebody uses it.
     *
     * Two declarers now. The reference dropdown moved into the shared library
     * when Meta-gen was split out - three components edit models with one, and
     * this one was carrying the mechanism for all three - so an asset named
     * `lib_yepr_gen.*` is checked against the library's own manifest in
     * `vendor/`, which is the copy this repository actually resolves against.
     */
    public function testEveryAssetALayoutUsesIsDeclared(): void
    {
        $assets = json_decode(
            (string) file_get_contents($this->root() . '/src/media/com_extengen/joomla.asset.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $declared = array_column($assets['assets'] ?? [], 'name');

        // The shared library declares its own, and ships them as library media.
        $libraryManifest = $this->root() . '/vendor/yepr/generator-core/media/joomla.asset.json';

        $this->assertFileExists($libraryManifest, 'The shared library has no asset manifest.');

        $library  = json_decode((string) file_get_contents($libraryManifest), true, 512, JSON_THROW_ON_ERROR);
        $declared = [...$declared, ...array_column($library['assets'] ?? [], 'name')];

        $missing = [];

        $root     = $this->root() . '/src/administrator/components/com_extengen/tmpl';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/use(?:Script|Style)\(\s*'([^']+)'/",
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] as $name) {
                if (!\in_array($name, $declared, true)) {
                    $missing[] = basename($file->getPathname()) . ': ' . $name;
                }
            }
        }

        $this->assertSame([], $missing, 'Layouts ask for assets nothing declares: ' . implode(', ', $missing));
    }

    /**
     * Every path the manifest names outside `<files>` is there too.
     *
     * `<files>` is not the only place a manifest points at the filesystem. The
     * installer also reads `<install><sql><file>`, its uninstall counterpart,
     * and `<update><schemas><schemapath>` - and it reads them without checking
     * first, so a path that is not in the package is an error dialog during
     * install rather than a missing feature afterwards.
     *
     * That is not hypothetical. `<schemapath type="mysql">sql/updates/mysql`
     * was declared and the folder did not exist, and the first attempt to
     * install the built package failed with "Path is not a folder". The
     * `<files>` checks above passed the whole time, because `sql` itself is
     * listed and does exist.
     */
    public function testEveryOtherPathTheManifestNamesIsThere(): void
    {
        $manifest = $this->manifest();
        $base     = $this->root() . '/src/administrator/components/com_extengen/';
        $missing  = [];

        foreach (['install', 'uninstall'] as $stage) {
            foreach ($manifest->{$stage}->sql->file ?? [] as $file) {
                if (!is_file($base . (string) $file)) {
                    $missing[] = $stage . ': ' . (string) $file;
                }
            }
        }

        foreach ($manifest->update->schemas->schemapath ?? [] as $path) {
            if (!is_dir($base . (string) $path)) {
                $missing[] = 'schemapath: ' . (string) $path;
            }
        }

        $this->assertSame([], $missing, 'The manifest names these and they are not there: ' . implode(', ', $missing));
    }

    /**
     * A declared schema path holds at least one version file.
     *
     * An empty folder passes the check above and is still wrong: Joomla reads
     * the highest version in it to fill `#__schemas`, so with none, a site has
     * no record of which schema it is on and no later update knows where to
     * start.
     */
    public function testTheSchemaPathHasAVersionToStartFrom(): void
    {
        $manifest = $this->manifest();
        $base     = $this->root() . '/src/administrator/components/com_extengen/';

        foreach ($manifest->update->schemas->schemapath ?? [] as $path) {
            $files = glob($base . (string) $path . '/*.sql') ?: [];

            $this->assertNotSame([], $files, (string) $path . ' holds no version file.');
        }
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

    /**
     * The composer dependency and the install script ask for the same library.
     *
     * Two places name a version and only one of them is checked at run time.
     * `composer.json` decides what the tests run against; `LIBRARY_MINIMUM`
     * decides what an installed site is allowed to have. Let them drift and the
     * suite passes against a library the package will refuse to install beside,
     * or - worse the other way round - a site accepts a library too old for the
     * code that was tested.
     *
     * That is not hypothetical. The build script picked the newest library zip
     * lying in the sibling checkout without comparing it to LIBRARY_MINIMUM at
     * all, so bumping the minimum produced a package whose own install script
     * rejected the library it carried.
     */
    public function testTheDeclaredDependencyMatchesTheInstallScript(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        preg_match("/LIBRARY_MINIMUM\s*=\s*'([^']+)'/", $script, $minimum);

        $composer = json_decode(
            (string) file_get_contents($this->root() . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $constraint = $composer['require']['yepr/generator-core'];

        $this->assertSame(
            '^' . implode('.', \array_slice(explode('.', $minimum[1]), 0, 2)),
            $constraint,
            'composer.json asks for ' . $constraint . ' but script.php insists on ' . $minimum[1] . '.'
        );
    }

    /**
     * The build refuses a library older than the install script demands.
     *
     * Checked by reading the build script rather than by building, because a
     * build needs the network and this needs to fail on the commit that breaks
     * it.
     */
    public function testTheBuildComparesTheLibraryVersionItPicks(): void
    {
        $build = (string) file_get_contents($this->root() . '/build/build.php');

        $this->assertStringContainsString(
            'version_compare($version, $required,',
            $build,
            'build.php picks a local library without checking it is new enough.'
        );
    }
}
