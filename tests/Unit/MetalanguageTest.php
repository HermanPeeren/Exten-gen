<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;

/**
 * What this component brings to the shared metalanguage store: step 3.4.
 *
 * Installing a package is the library's job and is tested there, against
 * packages built by hand. What is left here is the part that is about files in
 * this repository: the language this component ships, and the split that lets
 * a shipped language and an imported one take the same code path.
 *
 * @since  1.2.0
 */
final class MetalanguageTest extends TestCase
{
    /**
     * A project that names no language is written in ER1.
     *
     * Every project in every existing database is one of these, because there
     * was nothing else when they were made. Reading an empty binding as "no
     * language" would have made all of them unopenable on the day 3.4 shipped.
     */
    public function testAProjectThatNamesNoLanguageIsWrittenInTheBuiltIn(): void
    {
        $builtIn = Metalanguages::builtIn();

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
        $root = \dirname(__DIR__, 2) . '/src/';

        $this->assertFileExists(
            $root . Metalanguages::builtIn()->formRoot . 'project_chrome.xml'
        );
        $this->assertFileExists($root . Metalanguages::builtIn()->rootFormPath());

        // And the shipped one has no generated reference table, which is the
        // other half of what 3.5 removes.
        $this->assertSame('', Metalanguages::builtIn()->referenceTablePath());
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

        $names = static fn (\SimpleXMLElement $form): array => array_map(
            static fn (\SimpleXMLElement $f): string => (string) $f['name'],
            $form->xpath('//field') ?: []
        );

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
}
