<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;

/**
 * What an edit field can say that it could not: step 4.2.
 *
 * Stage 1 named three things the model cannot express, each invisible until
 * something needs it and then blocking a whole line of work: a custom form
 * field type, a custom validation rule, and tabs or subform layouts on a
 * generated form. 4.2 says to re-measure before adding anything, and the
 * measurement is most of the answer - 1.9's reference field and 3.5's field
 * classes removed the need for eight of the eleven custom classes Exten-gen
 * used to carry, and the rest of the gap turned out to be four optional
 * properties on one concept.
 *
 * ER1 1.1 is those four. They are optional, so every model stored under 1.0 is
 * a valid 1.1 model and a model that names none of them generates the same
 * bytes it generated before - which `GoldenOutputTest` is what says.
 *
 * **So this file exists because that is exactly the problem.** A change whose
 * whole success criterion is "the golden files did not move" has no test at all
 * until somebody writes one that uses the new thing. Every assertion below
 * would pass just as happily against the generator as it stood before 4.2, on
 * the fixtures that were already there.
 *
 * @since  1.4.0
 */
final class EditFieldGapsTest extends TestCase
{
    /**
     * The conference model, whose Participant page declares three edit fields.
     *
     * @param   array<string, mixed>  $extra  Merged into the first of them.
     */
    private function generate(array $extra): string
    {
        $model = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/conference.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        // Merged rather than unioned: `+=` keeps the left-hand value, so an
        // htmltype the fixture already has would have won and this whole file
        // would have been asserting the fixture back at itself.
        $model['pages']['pages1']['editfields']['editfields0'] = array_merge(
            $model['pages']['pages1']['editfields']['editfields0'],
            $extra
        );

        $files = GenerationHarness::run(json_decode(
            (string) json_encode($model, \JSON_THROW_ON_ERROR),
            false,
            512,
            \JSON_THROW_ON_ERROR
        ));

        $path = 'administrator/components/com_conference/forms/participant.xml';

        $this->assertTrue($files->has($path), 'The page this test is about was not generated.');

        return $files->get($path);
    }

    /**
     * A field class the model names, and where it lives.
     *
     * The gap as Stage 1 wrote it is "a generated extension cannot declare a
     * field type of its own", and the re-measure narrowed it: the fieldset a
     * generated form carries has always named the extension's *own* `Field`
     * namespace, so a class it declares resolves with no prefix at all. What
     * was missing is a way to say the name, and a way to point at a class that
     * lives somewhere else.
     */
    public function testAFieldMayNameAClassAndWhereItLives(): void
    {
        $xml = $this->generate([
            'htmltype'     => 'Speaker',
            'field_prefix' => 'Acme\\Component\\Talks\\Administrator\\Field',
        ]);

        $this->assertStringContainsString('type="Speaker"', $xml);
        $this->assertStringContainsString(
            'addfieldprefix="Acme\\Component\\Talks\\Administrator\\Field"',
            $xml
        );
    }

    /**
     * A validation rule, which the model could not say at all.
     *
     * Exten-gen's own `LetterRule` is the case this comes from: one rule, used
     * once, on a form the component does not generate. Nothing in the model had
     * a counterpart for it, so a generated extension could validate a value
     * only by the type of its input box.
     */
    public function testAFieldMayNameAValidationRuleAndWhereItLives(): void
    {
        $xml = $this->generate([
            'validate'    => 'Letter',
            'rule_prefix' => 'Acme\\Component\\Talks\\Administrator\\Rule',
        ]);

        $this->assertStringContainsString('validate="Letter"', $xml);
        $this->assertStringContainsString(
            'addruleprefix="Acme\\Component\\Talks\\Administrator\\Rule"',
            $xml
        );
    }

    /**
     * A prefix goes on the field, not on the fieldset.
     *
     * `Form::loadFile()` collects `addfieldprefix` from every element in the
     * document, so one field may carry its own without widening the search for
     * its neighbours. Writing it on the fieldset would make every field in the
     * form resolvable against somebody else's namespace, which is how a field
     * type silently becomes a different class than the one that was meant.
     */
    public function testThePrefixIsWrittenOnTheFieldItBelongsTo(): void
    {
        $xml = $this->generate([
            'htmltype'     => 'Speaker',
            'field_prefix' => 'Acme\\Component\\Talks\\Administrator\\Field',
        ]);

        $form = simplexml_load_string($xml);

        $this->assertNotFalse($form);

        $fieldsets = $form->xpath('//fieldset[@addfieldprefix="Acme\\Component\\Talks\\Administrator\\Field"]') ?: [];

        $this->assertSame([], $fieldsets, 'The borrowed namespace was written on the fieldset.');
    }

    /**
     * Fields naming a group are rendered together, and the rest stay put.
     *
     * The third gap: a generated form was one fieldset and everything went in
     * it. Whether a group becomes a tab is the template's business - Joomla
     * renders fieldsets as tabs or as blocks depending on the layout - and
     * "these belong together" is the part the model can honestly know.
     */
    public function testFieldsMayBeGroupedIntoTheirOwnFieldset(): void
    {
        $xml = $this->generate(['fieldset' => 'publication']);

        $form = simplexml_load_string($xml);

        $this->assertNotFalse($form);

        $named = $form->xpath('//fieldset[@name="publication"]/field') ?: [];

        $this->assertCount(1, $named, 'The grouped field is not in the group.');

        // And the others did not follow it. The default fieldset has no name,
        // so everything else is still where it was.
        $ungrouped = $form->xpath('//fieldset[not(@name)]/field') ?: [];

        $this->assertGreaterThan(1, \count($ungrouped), 'Everything moved into the group.');
    }

    /**
     * A model that says none of it generates what it always generated.
     *
     * The guard under the four rules above. `GoldenOutputTest` makes the same
     * statement over three whole components; this one makes it about this form
     * so that a failure here says which of the two things broke.
     */
    public function testAModelThatNamesNoneOfThemIsUnchanged(): void
    {
        $xml  = $this->generate([]);
        $form = simplexml_load_string($xml);

        $this->assertNotFalse($form);

        // On the fields, not in the document. The fieldset has carried the
        // extension's own Field and Rule namespaces since long before this,
        // which is the reason a class it declares needs no prefix at all.
        foreach (['addfieldprefix', 'validate', 'addruleprefix'] as $attribute) {
            $this->assertSame(
                [],
                $form->xpath('//field[@' . $attribute . ']') ?: [],
                $attribute . ' appeared on a field uninvited.'
            );
        }

        $this->assertSame([], $form->xpath('//fieldset[@name]') ?: [], 'A group appeared uninvited.');
    }
}
