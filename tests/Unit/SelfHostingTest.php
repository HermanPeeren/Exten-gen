<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;

/**
 * Exten-gen generating Exten-gen, and how far that gets: step 4.3.
 *
 * The plan set the criterion as *byte-identical output against the hand-written
 * component*, the way 2.3 checks a modelled generator. It is not met, and the
 * interesting part of 4.3 turned out to be why - so this file measures the
 * distance rather than asserting a thing that is not true.
 *
 * **`extengen.json` is a real model, not a sketch.** It describes the two
 * things this component stores, field for field out of
 * `sql/install.mysql.utf8.sql`, with an index page and a detail page for each -
 * and it sits in `Fixtures/golden/models` beside the other three, so its output
 * goes through every check they do: the golden comparison, the PHP, XML, INI
 * and SQL parsers, the name resolution, the template-tag sweep. Exten-gen
 * generates a valid component from a model of itself. That is worth having
 * separately from whether the bytes match.
 *
 * What the bytes say is below, and the number that explains it is the one in
 * `testMostOfThisComponentIsTheGeneratorItself`.
 *
 * @since  1.4.0
 */
final class SelfHostingTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function component(): string
    {
        return $this->root() . '/src/administrator/components/com_extengen';
    }

    /**
     * @return array<string, string>  The generated file set, path => contents.
     */
    private function generated(): array
    {
        $ast = json_decode(
            (string) file_get_contents($this->root() . '/tests/Fixtures/golden/models/extengen.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        );

        return GenerationHarness::run($ast)->all();
    }

    /**
     * The model describes the columns this component actually stores.
     *
     * A self-model that had drifted from the schema would make every number
     * below meaningless, and it would drift silently: nothing else reads it.
     * The Joomla chrome is deliberately absent - alias, published, access,
     * catid, ordering, params, language, checked_out and the publish dates are
     * what 3.2 decided a language cannot describe, and the generator supplies
     * them itself.
     */
    public function testTheModelDescribesTheSchemaItClaimsTo(): void
    {
        $model = json_decode(
            (string) file_get_contents($this->root() . '/tests/Fixtures/golden/models/extengen.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $modelled = [];

        foreach ($model['datamodel'] as $entity) {
            foreach ($entity['field'] as $field) {
                $modelled[strtolower($entity['entity_name'])][] = $field['field_name'];
            }
        }

        $sql = (string) file_get_contents($this->component() . '/sql/install.mysql.utf8.sql');

        $chrome = [
            'id', 'alias', 'checked_out_time', 'checked_out', 'params', 'ordering',
            'language', 'publish_down', 'publish_up', 'published', 'state', 'catid', 'access',
        ];

        foreach (
            [
                'project'      => '#__extengen_projects',
                'metalanguage' => '#__extengen_metalanguages',
            ] as $entity => $table
        ) {
            $start = strpos($sql, $table);

            $this->assertNotFalse($start, $table . ' is not in the install SQL.');

            $body = substr($sql, $start, (int) strpos($sql, 'ENGINE=InnoDB', $start) - $start);

            preg_match_all('/^\s*`([a-z_]+)`\s+[a-z]/mi', $body, $columns);

            $expected = array_values(array_diff($columns[1], $chrome));

            sort($expected);

            $found = $modelled[$entity] ?? [];

            sort($found);

            $this->assertSame($expected, $found, $table . ' and the model disagree about its columns.');
        }
    }

    /**
     * Most of this component is the generator, which no generator of CRUD
     * components can produce.
     *
     * **This is the answer to 4.3 and it is a number rather than an opinion.**
     * The criterion was written before anybody counted: a code generator is not
     * a thing a code generator for data-driven components makes, and half of
     * what is in here is the engine, the rule sets, the Twig template set and
     * the metalanguage machinery. Generating it byte for byte would need a
     * model of *generators*, which is Gen-gen's subject, not ER1's.
     *
     * The count is re-measured rather than recorded, so that the day the
     * balance shifts - the generator moving out to the library, say - this says
     * so instead of a sentence in a document nobody re-reads.
     */
    public function testMostOfThisComponentIsTheGeneratorItself(): void
    {
        $itsOwnMachinery = 0;
        $total           = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->component(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $total++;

            $path = str_replace('\\', '/', substr($file->getPathname(), \strlen($this->component()) + 1));

            foreach (
                [
                    'src/Generator/', 'generator_templates/', 'src/Metalanguage/',
                    'src/Reference/', 'src/Repository/', 'src/CustomCode/',
                    'src/Field/', 'src/Rule/',
                ] as $prefix
            ) {
                if (str_starts_with($path, $prefix)) {
                    $itsOwnMachinery++;

                    break;
                }
            }
        }

        $this->assertGreaterThan(
            $total / 2,
            $itsOwnMachinery,
            'Less than half of this component is now its own machinery, which changes what 4.3 is about.'
        );
    }

    /**
     * What it does generate lands on the paths the component uses.
     *
     * The shape is right even where the bytes are not: the manifest, the
     * services provider, the SQL, the language files, and a controller, model,
     * table, view and layout per entity, at the paths this component has them
     * at. A generator that produced the same number of files under names
     * nothing recognises would be much further away than these numbers suggest.
     */
    public function testTheFilesItGeneratesLandWhereTheComponentKeepsThem(): void
    {
        $generated = $this->generated();
        $existing  = 0;

        foreach (array_keys($generated) as $path) {
            if (is_file($this->root() . '/src/' . $path)) {
                $existing++;
            }
        }

        $this->assertGreaterThan(20, $existing, 'Hardly any generated file has a counterpart here.');

        // And the ones with no counterpart are the second entity's screens plus
        // the site half, which this component does not have because nothing has
        // ever needed them - not misplaced files.
        $this->assertLessThan(
            \count($generated) / 2,
            \count($generated) - $existing,
            'More than half the output has nowhere to land.'
        );
    }

    /**
     * And the criterion itself, which is not met.
     *
     * Stated as an assertion so that it cannot quietly stop being true. If a
     * change ever makes one of these files match, this fails - and that is the
     * right outcome: it is news, and the plan is where it belongs.
     *
     * The distance is not mysterious. `services/provider.php` comes out the
     * same length as the hand-written one and differs in its header comment and
     * the order of its imports; `ProjectModel.php` is a quarter of the size of
     * the real one because the real one merges an imported metalanguage's forms
     * onto the Joomla half. The first kind is cosmetic, the second is custom
     * code that belongs in a slot, and neither is what the step was really for.
     */
    public function testNothingIsByteIdenticalYet(): void
    {
        $identical = [];

        foreach ($this->generated() as $path => $contents) {
            $hand = $this->root() . '/src/' . $path;

            if (!is_file($hand)) {
                continue;
            }

            $existing = str_replace("\r\n", "\n", (string) file_get_contents($hand));

            if ($existing === str_replace("\r\n", "\n", $contents)) {
                $identical[] = $path;
            }
        }

        $this->assertSame(
            [],
            $identical,
            'These now match the hand-written component, which 4.3 said was the criterion: '
            . implode(', ', $identical)
        );
    }
}
