<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Metalanguage\MetalanguageEntry;
use Yepr\Component\Extengen\Administrator\Metalanguage\MetalanguageInstaller;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageManifest;

/**
 * Importing a metalanguage, and being written in one: step 3.4.
 *
 * **The packages here are built by hand**, a few files at a time, rather than
 * run through Meta-gen's generator. This component cannot load Meta-gen's code
 * and should not want to: what it depends on is the *format*, which is the
 * library's, and a fixture produced by the other side would make these tests
 * fail when that side changed something these tests are not about.
 *
 * What is checked is what this component does with a package: refuse the ones
 * it cannot install, unpack the rest where they say they go, and open a
 * project with the forms that came out.
 *
 * @since  1.1.0
 */
final class MetalanguageTest extends TestCase
{
    /**
     * Paths written during a test, removed afterwards.
     *
     * @var string[]
     */
    private array $rubbish = [];

    protected function tearDown(): void
    {
        foreach ($this->rubbish as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                self::removeTree($path);
            }
        }

        $this->rubbish = [];

        parent::tearDown();
    }

    /**
     * A small language, packaged the way Meta-gen packages one.
     *
     * @param  array<string, string>  $extra     Files to add after the manifest is built.
     * @param  string|null            $rootName  The root classifier, or null for a language with none.
     */
    private function package(array $extra = [], ?string $rootName = 'Thing'): string
    {
        $files = new FileCollection();
        $root  = MetalanguagePackage::installRoot('Small', '1.0');

        $files->add(MetalanguagePackage::MODEL, "{\"name\":\"Small\",\"version\":\"1.0\"}\n");
        $files->add(
            MetalanguagePackage::formPath('Thing'),
            '<?xml version="1.0"?><form><fieldset name="entities">'
            . '<field name="thingName" type="text" label="YEPR_SMALL_THING_FIELD_NAME_LABEL"/>'
            . '</fieldset></form>'
        );
        $files->add(MetalanguagePackage::REFERENCES, "{}\n");
        $files->add(
            MetalanguagePackage::languagePath('Small'),
            "YEPR_SMALL_THING_FIELD_NAME_LABEL=\"What it is called\"\n"
        );

        $hashes = [];

        foreach ($files as $path => $contents) {
            $hashes[$path] = hash('sha256', $contents);
        }

        ksort($hashes);

        $files->add(MetalanguagePackage::MANIFEST, (new PackageManifest(
            'Small',
            'Small',
            '1.0',
            $rootName ?? '',
            $root,
            MetalanguagePackage::languagePath('Small'),
            MetalanguagePackage::TAG,
            $hashes,
            MetalanguagePackage::FORMAT,
            '2026-09-22T00:00:00+00:00'
        ))->toJson());

        foreach ($extra as $path => $contents) {
            $files->add($path, $contents);
        }

        $archive = sys_get_temp_dir() . '/extengen-package-' . bin2hex(random_bytes(6)) . '.zip';

        $this->rubbish[] = $archive;

        (new ZipWriter())->write($files, $archive);

        return $archive;
    }

    /**
     * A site root nothing else is writing into.
     */
    private function site(): string
    {
        $root = sys_get_temp_dir() . '/extengen-site-' . bin2hex(random_bytes(6));

        $this->rubbish[] = $root;

        mkdir($root, 0755, true);

        return $root;
    }

    /**
     * A package installs where its manifest says it goes, unchanged.
     *
     * Byte for byte, because that is the whole reason the package carries an
     * install root rather than being relocated on the way in: a subform's
     * `formsource` was written against this directory when the forms were
     * generated, so rewriting anything here would mean rewriting those too.
     */
    public function testAPackageUnpacksWhereItSaysItGoesAndIsNotTouched(): void
    {
        $site  = $this->site();
        $entry = (new MetalanguageInstaller($site))->install($this->package());

        $this->assertSame('Small', $entry->key);
        $this->assertSame('1.0', $entry->version);
        $this->assertSame('Thing', $entry->root);
        $this->assertSame('media/yepr_metalanguages/Small/1.0/', $entry->formRoot);

        $form = $site . '/' . $entry->rootFormPath();

        $this->assertFileExists($form);
        $this->assertStringContainsString('YEPR_SMALL_THING_FIELD_NAME_LABEL', (string) file_get_contents($form));

        $this->assertFileExists($site . '/' . $entry->referenceTablePath());
        $this->assertFileExists($site . '/' . rtrim($entry->formRoot, '/') . '/' . $entry->languageFile);
    }

    /**
     * A package that does not describe itself correctly installs nothing.
     *
     * Nothing, rather than as much of it as was sound: a language with one
     * form missing opens at the root and renders an empty box three levels
     * down, and reports nothing anywhere.
     */
    public function testABadPackageInstallsNothingAtAll(): void
    {
        $site    = $this->site();
        $archive = $this->package(['forms/sneaked-in.xml' => '<form/>']);

        try {
            (new MetalanguageInstaller($site))->install($archive);

            $this->fail('A package the manifest does not account for was installed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not in the manifest', $e->getMessage());
        }

        $this->assertSame(
            [],
            glob($site . '/media/*') ?: [],
            'Something was written before the package was refused.'
        );
    }

    /**
     * A language with no root classifier is refused, and says why.
     *
     * It packages fine - being half written is an ordinary state for a
     * language in Meta-gen - but there is no form a project in it would open
     * at, so importing it would put a choice in the dropdown that cannot be
     * chosen.
     */
    public function testALanguageWithNoRootIsRefusedWithAnExplanation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no root classifier/');

        (new MetalanguageInstaller($this->site()))->install($this->package([], null));
    }

    /**
     * A zip that names a path outside itself writes nothing.
     *
     * The files came out of an upload rather than through a `FileCollection`,
     * so nothing has refused traversal on the way in. A zip may say
     * `../../configuration.php` and some unpackers will oblige.
     */
    public function testAPackageCannotWriteOutsideItsOwnDirectory(): void
    {
        $site    = $this->site();
        $archive = sys_get_temp_dir() . '/extengen-evil-' . bin2hex(random_bytes(6)) . '.zip';

        $this->rubbish[] = $archive;

        // Built through ZipArchive directly: FileCollection refuses this path,
        // which is exactly why it cannot be used to write the test.
        $sound = new \ZipArchive();

        $sound->open($archive, \ZipArchive::CREATE);
        $sound->addFromString('../../escaped.txt', 'nope');
        $sound->addFromString(MetalanguagePackage::MANIFEST, '{"format":1}');
        $sound->close();

        try {
            (new MetalanguageInstaller($site))->install($archive);

            $this->fail('A package naming a path outside itself was installed.');
        } catch (\RuntimeException $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $this->assertFileDoesNotExist(\dirname($site) . '/escaped.txt');
        $this->assertFileDoesNotExist($site . '/escaped.txt');
    }

    /**
     * A project that names no language is written in ER1.
     *
     * Every project in every existing database is one of these, because there
     * was nothing else when they were made. Reading an empty binding as "no
     * language" would have made all of them unopenable on the day this
     * shipped.
     */
    public function testAProjectThatNamesNoLanguageIsWrittenInTheBuiltIn(): void
    {
        $builtIn = MetalanguageEntry::builtIn();

        $this->assertTrue($builtIn->answersTo('', ''));
        $this->assertTrue($builtIn->isBuiltIn());
        $this->assertFalse($builtIn->answersTo('Small', '1.0'));
    }

    /**
     * The built-in and an imported language have the same shape.
     *
     * Chrome plus one root form, either way - which is what lets 3.5 turn ER1
     * into a package without changing anything that opens a project.
     */
    public function testTheBuiltInIsShapedLikeAnImportedLanguage(): void
    {
        $root = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/';

        $this->assertFileExists($root . 'forms/project_chrome.xml');
        $this->assertFileExists(
            \dirname(__DIR__, 2) . '/src/' . MetalanguageEntry::builtIn()->rootFormPath()
        );
    }

    /**
     * The split put the Joomla half in one file and the model half in the other.
     *
     * Checked because the two were one file until 3.4, and a field that ended
     * up in neither would be a field that silently stopped being on the form -
     * no error, just a value that stops being saved.
     */
    public function testTheProjectFormSplitLostNothing(): void
    {
        $forms  = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/forms/';
        $chrome = simplexml_load_file($forms . 'project_chrome.xml');
        $model  = simplexml_load_file($forms . 'project_er1.xml');

        $this->assertNotFalse($chrome);
        $this->assertNotFalse($model);

        $names = static function (\SimpleXMLElement $form): array {
            return array_map(
                static fn (\SimpleXMLElement $f): string => (string) $f['name'],
                $form->xpath('//field') ?: []
            );
        };

        $inChrome = $names($chrome);
        $inModel  = $names($model);

        // The Joomla item half, which 3.2 recorded as not derivable from a
        // language at all.
        foreach (['id', 'name', 'alias', 'published', 'catid', 'access', 'ordering'] as $field) {
            $this->assertContains($field, $inChrome, $field . ' left the form entirely.');
        }

        // And the model half.
        foreach (['datamodel', 'pages', 'extensions'] as $field) {
            $this->assertContains($field, $inModel, $field . ' left the form entirely.');
        }

        // The binding itself, which is new.
        $this->assertContains('metalanguage', $inChrome);

        // And nothing is in both, which would post two inputs to one key and
        // let the later one win without saying so.
        $this->assertSame(
            [],
            array_values(array_intersect($inChrome, $inModel)),
            'These are in both halves of the project form.'
        );
    }

    /**
     * A directory and everything under it.
     */
    private static function removeTree(string $root): void
    {
        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($tree as $entry) {
            if ($entry instanceof \SplFileInfo) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
        }

        rmdir($root);
    }
}
