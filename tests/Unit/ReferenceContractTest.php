<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Reference\ReferenceIndex;

/**
 * The two halves of the reference mechanism still describe the same thing.
 *
 * PHP decides what an object type is - where it lives in the model, the class
 * on its name input, how that input's element id relates to the hidden id
 * beside it - and puts that description in the page. JavaScript reads it. There
 * is no moment at which either could notice the other had changed, so the
 * places they have to agree are listed here.
 *
 * The form XML is the third party. A field saying `objecttype="Entity"` is a
 * promise that the index will carry an `Entity` list; nothing at runtime checks
 * it, and an unknown type shows an empty dropdown with no error anywhere.
 */
final class ReferenceContractTest extends TestCase
{
    /**
     * Every objecttype a form asks for is one *its own* model indexes.
     *
     * Two models, two indices, and which one a form belongs to is decided by
     * where it lives: everything under `metaProjectForms/` describes a
     * projectForm in LionCore M3, everything else describes a project in ER1.
     *
     * Checked per model rather than against the union of both, because the
     * union would accept `objecttype="Entity"` on a meta-model form - a
     * dropdown that renders empty on every screen, since the projectForm index
     * has no Entity list and never will.
     */
    public function testEveryObjectTypeTheFormsUseIsIndexed(): void
    {
        $project     = array_keys(ReferenceIndex::project()->clientTypes());
        $projectForm = array_keys(ReferenceIndex::projectForm()->clientTypes());

        $unknown = [];
        $found   = 0;

        foreach ($this->formFiles() as $relative => $xml) {
            $isMeta = str_starts_with($relative, 'metaProjectForms/');
            $known  = $isMeta ? $projectForm : $project;

            foreach ($xml->xpath('//field[@objecttype]') ?: [] as $field) {
                $found++;

                $type = (string) $field['objecttype'];

                if (!\in_array($type, $known, true)) {
                    $unknown[] = $relative . ': ' . $type
                        . ' (that model offers ' . implode(', ', $known) . ')';
                }
            }
        }

        $this->assertGreaterThan(0, $found, 'No reference fields at all, which cannot be right.');

        $this->assertSame(
            [],
            $unknown,
            "These forms point at object types their own model does not carry:
  "
            . implode("
  ", $unknown)
        );
    }

    /**
     * And both models are actually used by some form.
     *
     * Without this the rule above passes by having nothing to check the day
     * somebody moves the meta-model forms somewhere else.
     */
    public function testBothModelsHaveFormsPointingAtThem(): void
    {
        $meta = 0;
        $er1  = 0;

        foreach ($this->formFiles() as $relative => $xml) {
            $count = \count($xml->xpath('//field[@objecttype]') ?: []);

            if (str_starts_with($relative, 'metaProjectForms/')) {
                $meta += $count;
            } else {
                $er1 += $count;
            }
        }

        $this->assertGreaterThan(0, $er1, 'No ER1 form uses a reference field.');
        $this->assertGreaterThan(0, $meta, 'No meta-model form uses a reference field.');
    }

    /**
     * A field that scopes itself names a sibling that is really there.
     *
     * `scope="entity_reference_id"` means "the parent I belong to is in the
     * field called entity_reference_id, in this same repeating row". A typo
     * there produces a dropdown scoped to nothing, which looks like an empty
     * list rather than like a mistake.
     */
    public function testEveryScopeNamesAFieldInTheSameForm(): void
    {
        $missing = [];

        foreach ($this->formFiles() as $relative => $xml) {
            $names = [];

            foreach ($xml->xpath('//field[@name]') ?: [] as $field) {
                $names[] = (string) $field['name'];
            }

            foreach ($xml->xpath('//field[@scope]') ?: [] as $field) {
                $scope = (string) $field['scope'];

                if (!\in_array($scope, $names, true)) {
                    $missing[] = $relative . ': ' . $scope;
                }
            }
        }

        $this->assertSame([], $missing, 'These scopes name no field beside them: ' . implode(', ', $missing));
    }

    /**
     * Every type the index describes is one the client knows how to find.
     */
    public function testEveryIndexedTypeTellsTheClientHowToFindItsRows(): void
    {
        foreach (ReferenceIndex::project()->clientTypes() as $type => $descriptor) {
            foreach (['selector', 'nameToken', 'idToken'] as $required) {
                $this->assertArrayHasKey($required, $descriptor, $type . ' does not say its ' . $required);
                $this->assertNotSame('', $descriptor[$required], $type . ' has an empty ' . $required);
            }
        }
    }

    /**
     * A class the client looks for is a class the forms put on a name input.
     *
     * This is the join that has no other check: `selector` is a string in PHP
     * and a class attribute in XML, and if they part company the dropdown stops
     * seeing objects that are on screen but not yet saved - which is the exact
     * failure this whole step existed to fix, returning silently.
     */
    public function testEveryNameInputClassTheClientLooksForIsOnAFieldSomewhere(): void
    {
        $classes = [];

        foreach ($this->formFiles() as $xml) {
            foreach ($xml->xpath('//field[@class]') ?: [] as $field) {
                foreach (preg_split('/\s+/', (string) $field['class']) ?: [] as $class) {
                    $classes[$class] = true;
                }
            }
        }

        foreach (ReferenceIndex::project()->clientTypes() as $type => $descriptor) {
            $this->assertArrayHasKey(
                $descriptor['selector'],
                $classes,
                $type . ' is found by the class "' . $descriptor['selector'] . '", which no form puts on a field.'
            );
        }
    }

    /**
     * No form calls a JavaScript function by name any more.
     *
     * Three of the names they did call - `editChildConceptList`,
     * `editConceptFieldsList`, `backupClassifierKey` - had no definition
     * anywhere, so every change to those fields threw. Nothing in a form or a
     * template could have caught that; the browser console was the only place
     * it showed. Events are delegated from the document now, which also means a
     * repeating row added after load behaves like one that was there.
     */
    public function testNoFormWiresAnInlineHandler(): void
    {
        $offenders = [];

        foreach ($this->formFiles() as $relative => $xml) {
            foreach ($xml->xpath('//field[@onchange]') ?: [] as $field) {
                $handler = (string) $field['onchange'];

                // Submitting the form the field is in needs no function.
                if (str_contains($handler, 'this.form.submit()')) {
                    continue;
                }

                $offenders[] = $relative . ': ' . $handler;
            }
        }

        $this->assertSame([], $offenders, 'These name a function from a form: ' . implode(', ', $offenders));
    }

    /** @return array<string, \SimpleXMLElement> */
    private function formFiles(): array
    {
        $root  = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/forms';
        $forms = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'xml') {
                continue;
            }

            $xml = simplexml_load_file($file->getPathname());

            $this->assertNotFalse($xml, $file->getPathname() . ' is not valid XML.');

            $relative         = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
            $forms[$relative] = $xml;
        }

        ksort($forms);

        return $forms;
    }
}
