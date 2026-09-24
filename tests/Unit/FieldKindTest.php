<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;
use Yepr\Component\Extengen\Tests\Support\FormSpelling;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Component\Extengen\Tests\Support\ShippedLanguage;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Package\PackageReader;

/**
 * A field is a property or a reference, whoever spelled it.
 *
 * **The defect this closes was live and silent.** ER1's `Field` is abstract over
 * `Property` and `EntityReferenceField`; 3.5 made ER1 a generated language, and
 * a generated form writes the discriminator's options from the subtype *concept
 * names* and the subform from the same name with a small first letter. The
 * hand-written forms it replaced had used `property` and `reference`, and every
 * generator compared against those - so a project modelled through Exten-gen's
 * own screens since 3.5 generated a component with no relations in it. No
 * foreign key, no join, no dropdown, and no error anywhere.
 *
 * Nothing caught it because every model in the suite predates 3.5. The golden
 * fixtures, the seeded projects, the browser specs: all of them store the old
 * spelling, so all of them kept passing.
 *
 * So this file asks the question two ways. The names `FieldKind` uses are
 * checked against the shipped language rather than trusted, and the generated
 * output is compared between a model spelled the old way and the same model
 * spelled the way the forms write it now.
 *
 * @since  1.4.0
 */
final class FieldKindTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function models(): array
    {
        return array_map(
            static fn (string $p): array => [basename($p, '.json')],
            glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: []
        );
    }

    /**
     * @param  array<string, mixed>  $model
     */
    private function generate(array $model): FileCollection
    {
        return GenerationHarness::run(
            json_decode((string) json_encode($model, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Both spellings produce the same bytes.
     *
     * The whole claim, over every fixture. Anything less than byte-identical
     * would mean a project generates differently depending on when it was
     * written, which is the defect wearing a smaller hat.
     */
    #[DataProvider('models')]
    public function testBothSpellingsGenerateTheSameThing(string $name): void
    {
        $model = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $old = $this->generate($model);
        $new = $this->generate(FormSpelling::asGeneratedFormsWriteIt($model));

        $this->assertSame($old->paths(), $new->paths(), $name . ' generates a different file set.');

        foreach ($old->all() as $path => $contents) {
            $this->assertSame($contents, $new->get($path), $name . ': ' . $path . ' differs.');
        }
    }

    /**
     * And the fixtures actually exercise a reference, or this proves nothing.
     *
     * Two identical models generate identically whatever the code does. The
     * guard is that at least one fixture has a field the rewrite touches - and
     * the count is asserted, because a rewrite that silently matched nothing
     * would make every assertion above vacuous.
     */
    public function testTheFixturesHaveReferencesToRewrite(): void
    {
        $rewritten = 0;

        foreach (self::models() as [$name]) {
            $model = json_decode(
                (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json'),
                true,
                512,
                \JSON_THROW_ON_ERROR
            );

            foreach ($model['datamodel'] ?? [] as $entity) {
                foreach ($entity['field'] ?? [] as $field) {
                    if (($field['field_type'] ?? '') === 'reference') {
                        $rewritten++;
                    }
                }
            }
        }

        $this->assertGreaterThan(2, $rewritten, 'No fixture has a reference field, so the comparison is empty.');
    }

    /**
     * A reference spelled the new way is recognised as one.
     *
     * Stated directly as well as through the output, because "the two agree" is
     * also true of a pair that both get it wrong.
     */
    public function testAReferenceIsRecognisedEitherWay(): void
    {
        $old = (object) ['field_type' => 'reference', 'reference' => (object) ['reference' => 'e1']];
        $new = (object) [
            'field_type'                      => FieldKind::REFERENCE,
            lcfirst(FieldKind::REFERENCE)     => (object) ['reference' => 'e1'],
        ];

        foreach ([$old, $new] as $field) {
            $this->assertTrue(FieldKind::isReference($field));
            $this->assertFalse(FieldKind::isProperty($field));
            $this->assertSame('e1', FieldKind::reference($field)->reference);
        }
    }

    /**
     * And a property, the same both ways.
     */
    public function testAPropertyIsRecognisedEitherWay(): void
    {
        $old = (object) ['field_type' => 'property', 'property' => (object) ['type' => 'Short_Text']];
        $new = (object) ['field_type' => FieldKind::PROPERTY, 'property' => (object) ['type' => 'Short_Text']];

        foreach ([$old, $new] as $field) {
            $this->assertTrue(FieldKind::isProperty($field));
            $this->assertFalse(FieldKind::isReference($field));
            $this->assertSame('Short_Text', FieldKind::property($field)->type);
        }
    }

    /**
     * A field that says nothing is neither, and asking costs nothing.
     */
    public function testAFieldWithNoKindIsNeither(): void
    {
        $this->assertFalse(FieldKind::isProperty((object) []));
        $this->assertFalse(FieldKind::isReference((object) []));
        $this->assertNull(FieldKind::property((object) []));
        $this->assertNull(FieldKind::reference((object) []));
    }

    /**
     * The subtypes are concepts the language declares.
     *
     * Checked rather than trusted, because a constant nobody compares against
     * the thing it names is precisely how this defect happened. Once checked,
     * the ancestry guard keeps them true: a derived language may rename neither.
     */
    public function testTheSubtypesAreTheLanguagesOwnConcepts(): void
    {
        $concepts = array_column(
            PackageReader::fromZip(ShippedLanguage::package())->manifest()->concepts,
            'name'
        );

        $this->assertContains(FieldKind::PROPERTY, $concepts);
        $this->assertContains(FieldKind::REFERENCE, $concepts);
    }

    /**
     * And the discriminator is the field the shipped form actually writes.
     *
     * `field_type` is *not* a feature of the language - it is the name Meta-gen
     * gives the radio that chooses between an abstract concept's subtypes, so
     * the manifest cannot confirm it and the form is the only witness there is.
     * It is also the better one: this is the exact artefact the edit screen
     * saves a model through, so what it says is what a stored model contains.
     *
     * Three claims, all of which the generators depend on and none of which they
     * may assume:
     *
     * - the discriminator is named `field_type`;
     * - its options are the two subtype concept names, spelled as concepts;
     * - the payload sits under the concept name with a small first letter, which
     *   is the rule `FieldKind` derives rather than a name it stores.
     *
     * The last is the one that broke. Asserting it against the generated form
     * means that if Meta-gen ever changes how it names a subform, this fails
     * here instead of silently dropping every relation from a generated
     * component.
     */
    public function testTheFormWritesWhatFieldKindReads(): void
    {
        $field = ShippedLanguage::forms()['forms/field.xml'] ?? null;

        $this->assertNotNull($field, 'The shipped package has no field form.');

        $names   = [];
        $showon  = [];

        foreach ($field->xpath('//field') ?: [] as $element) {
            $name          = (string) $element['name'];
            $names[$name]  = $element;
            $showon[$name] = (string) $element['showon'];
        }

        $this->assertArrayHasKey(
            FieldKind::DISCRIMINATOR,
            $names,
            'The form does not have the field the generators read to tell a property from a reference.'
        );

        $options = array_map(
            static fn (\SimpleXMLElement $o): string => (string) $o['value'],
            $names[FieldKind::DISCRIMINATOR]->xpath('option') ?: []
        );

        $this->assertEqualsCanonicalizing([FieldKind::PROPERTY, FieldKind::REFERENCE], $options);

        foreach ([FieldKind::PROPERTY, FieldKind::REFERENCE] as $subtype) {
            $payload = lcfirst($subtype);

            $this->assertArrayHasKey(
                $payload,
                $names,
                'The form has no subform named ' . $payload . ', so that is not where ' . $subtype . ' is stored.'
            );

            $this->assertSame(
                FieldKind::DISCRIMINATOR . ':' . $subtype,
                $showon[$payload],
                $payload . ' is shown for a different value than the one it carries.'
            );
        }
    }
}
