<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Component\Extengen\Tests\Support\ShippedLanguage;
use Yepr\Gen\Core\Package\PackageReader;

/**
 * The language this component ships: step 3.5.
 *
 * It used to be twenty-four hand-written form files loaded straight out of
 * `forms/`, and `MetalanguageEntry::builtIn()` was the entry that stood for
 * them. ER1 is modelled in LionCore M3 now and ships as a package, so there is
 * no built-in and the tests about one are gone with it.
 *
 * What replaced them is the question that actually matters: **can this
 * component open a project with what it ships?** A package that is missing a
 * form, or whose root classifier names a form that is not in it, is a component
 * whose own language does not work - and that is a thing the shipped artefact
 * can be asked, once, here.
 *
 * @since  1.2.0
 */
final class MetalanguageTest extends TestCase
{
    /**
     * The shipped package describes itself correctly.
     *
     * The same question `MetalanguageImporter` asks before it writes anything,
     * asked of the package this component carries - because a component whose
     * own language would be refused on import is one that installs and then
     * cannot open a project.
     */
    public function testTheShippedPackageWouldSurviveBeingImported(): void
    {
        $reader = PackageReader::fromZip(ShippedLanguage::package());

        $this->assertSame([], $reader->problems());

        $manifest = $reader->manifest();

        $this->assertSame(Metalanguages::SHIPPED, $manifest->key);
        $this->assertSame('Project', $manifest->root, 'a project opens at a Project');
        $this->assertNotSame([], $manifest->concepts, 'nothing could be selected in a rule');
    }

    /**
     * Every form the shipped language reaches is in the package.
     *
     * A `formsource` pointing at a file that is not there renders as an empty
     * box with nothing reported anywhere, which is the failure this family has
     * met at 3.1, at 3.0 and again at 3.3. Asking it of the artefact means the
     * answer covers what a site installs rather than what a working copy has.
     */
    public function testEverySubformInTheShippedPackageIsInIt(): void
    {
        $reader   = PackageReader::fromZip(ShippedLanguage::package());
        $manifest = $reader->manifest();
        $files    = $reader->files();
        $missing  = [];
        $checked  = 0;

        foreach ($reader->forms() as $path => $contents) {
            $xml = simplexml_load_string($contents);

            $this->assertNotFalse($xml, $path . ' is not XML.');

            foreach ($xml->xpath('//field[@formsource]') ?: [] as $field) {
                $source = (string) $field['formsource'];
                $checked++;

                if (!str_starts_with($source, $manifest->formRoot)) {
                    $missing[] = $path . ' -> ' . $source . ' points outside the package';

                    continue;
                }

                if (!isset($files[substr($source, \strlen($manifest->formRoot))])) {
                    $missing[] = $path . ' -> ' . $source;
                }
            }
        }

        $this->assertSame([], $missing, implode("\n  ", $missing));
        $this->assertGreaterThan(10, $checked, 'no subform was checked, so this proves nothing');
    }

    /**
     * The Joomla half of a project's form is still this component's own.
     *
     * 3.2 recorded why it is not generated: alias, published, access, catid,
     * ordering and params are not derivable from a language at all. A project
     * is a Joomla item as well as a model, and only the second half comes out
     * of a package - so `project_chrome.xml` stays after the other twenty-four
     * forms have gone.
     */
    public function testTheJoomlaHalfOfTheFormIsStillShipped(): void
    {
        $forms  = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/forms/';
        $chrome = simplexml_load_file($forms . 'project_chrome.xml');

        $this->assertNotFalse($chrome);

        $names = array_map(
            static fn (\SimpleXMLElement $f): string => (string) $f['name'],
            $chrome->xpath('//field') ?: []
        );

        foreach (['id', 'name', 'alias', 'published', 'catid', 'access', 'ordering'] as $field) {
            $this->assertContains($field, $names, $field . ' left the form entirely.');
        }

        // And the binding, which is how the other half is found at all.
        $this->assertContains('metalanguage', $names);

        // The model half is gone from this repository - it is in the package.
        $this->assertFileDoesNotExist($forms . 'project_er1.xml');
        $this->assertFileDoesNotExist($forms . 'entity.xml');
    }

    /**
     * Nothing in this component reads the old forms directory any more.
     *
     * Twenty-four files left at once. A path still naming one of them would be
     * a screen that renders an empty box, and that is exactly the kind of
     * leftover a grep finds and a test run does not.
     */
    public function testNothingStillNamesTheFormsThatWent(): void
    {
        $root    = \dirname(__DIR__, 2) . '/src';
        $gone    = ['project_er1.xml', 'entity.xml', 'field.xml', 'page.xml', 'property.xml'];
        $naming  = [];
        $scanned = 0;

        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if (!\in_array($file->getExtension(), ['php', 'xml'], true)) {
                continue;
            }

            // The generator's own templates describe a *generated* component's
            // forms, which are not these.
            if (str_contains(str_replace('\\', '/', $file->getPathname()), '/generator_templates/')) {
                continue;
            }

            $scanned++;
            $source = (string) file_get_contents($file->getPathname());

            foreach ($gone as $form) {
                if (str_contains($source, 'forms/' . $form)) {
                    $naming[] = basename($file->getPathname()) . ' names forms/' . $form;
                }
            }
        }

        $this->assertGreaterThan(20, $scanned, 'the scan reached almost nothing');
        $this->assertSame([], $naming, implode("\n  ", $naming));
    }
}
