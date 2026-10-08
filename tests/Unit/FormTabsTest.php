<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Metalanguage\FormTabs;
use Yepr\Component\Extengen\Tests\Support\ShippedLanguage;

/**
 * A project's tabs come from its language's root form: step 5.2.
 *
 * The first test is the step's own claim about ER1, read off the package this
 * component ships rather than off a fixture written for the test. The others
 * are the cases a language Meta-gen produces can be in: no fieldsets at all,
 * which is every language before 5.2, and a mixture.
 *
 * `project-binds.cy.js` checks the same three tabs on the real screen.
 *
 * @since  1.3.0
 */
final class FormTabsTest extends TestCase
{
    private function shippedRootForm(): string
    {
        $forms = ShippedLanguage::forms();

        $this->assertArrayHasKey('forms/project.xml', $forms);

        return (string) $forms['forms/project.xml']->asXML();
    }

    public function testEr1OpensOnEntitiesPagesAndExtensions(): void
    {
        $tabs = FormTabs::fromXml($this->shippedRootForm(), 'ER1');

        $this->assertSame(['entities', 'pages', 'extensions'], array_keys($tabs->tabs));
        $this->assertSame(['datamodel'], $tabs->tabs['entities']['fields']);
        $this->assertSame(['pages'], $tabs->tabs['pages']['fields']);
        $this->assertSame(['extensions'], $tabs->tabs['extensions']['fields']);
        $this->assertSame('YEPR_ER1_PROJECT_FIELDSET_ENTITIES_LABEL', $tabs->tabs['entities']['label']);

        // Nothing in ER1's unnamed fieldset is visible, so there is no tab for it.
        $this->assertSame(['project_id', 'LIonWeb_key'], $tabs->hidden);
        $this->assertSame('entities', $tabs->first());
        $this->assertSame('entities', $tabs->tabOf('datamodel'));
    }

    public function testALanguageWithNoFieldsetsOpensOnOneTabNamedAfterIt(): void
    {
        $tabs = FormTabs::fromXml(
            '<form><fieldset><field name="title" type="text"/><field name="body" type="textarea"/>'
            . '<field name="LIonWeb_key" type="hidden"/></fieldset></form>',
            'Testlang'
        );

        $this->assertSame([FormTabs::UNNAMED => ['label' => 'Testlang', 'fields' => ['title', 'body']]], $tabs->tabs);
        $this->assertSame(['LIonWeb_key'], $tabs->hidden);
    }

    public function testUngroupedFieldsComeFirstAndGroupsKeepTheirOrder(): void
    {
        $tabs = FormTabs::fromXml(
            '<form><fieldset><field name="a" type="text"/></fieldset>'
            . '<fieldset name="second" label="L2"><field name="b" type="text"/></fieldset>'
            . '<fieldset name="first"><field name="c" type="text"/></fieldset>'
            . '<fieldset name="second"><field name="d" type="text"/></fieldset></form>',
            'X'
        );

        $this->assertSame([FormTabs::UNNAMED, 'second', 'first'], array_keys($tabs->tabs));
        $this->assertSame(['b', 'd'], $tabs->tabs['second']['fields'], 'one name is one group');
        $this->assertSame('L2', $tabs->tabs['second']['label']);
        $this->assertSame('First', $tabs->tabs['first']['label'], 'a group with no label is named for itself');
        $this->assertNull($tabs->tabOf('nowhere'));
    }

    public function testAFormThatDoesNotParseHasNoTabs(): void
    {
        $tabs = FormTabs::fromXml('', 'X');

        $this->assertSame([], $tabs->tabs);
        $this->assertSame(FormTabs::UNNAMED, $tabs->first());
    }
}
