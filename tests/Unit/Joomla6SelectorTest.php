<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Rules\Joomla6Selectors;
use Yepr\Component\Extengen\Administrator\Reference\Er1;
use Yepr\Gen\Core\Reference\ReferenceIndex;
use Yepr\Gen\Core\Rule\DataSelectors;
use Yepr\Gen\Core\Rule\Vocabulary;
use Yepr\Gen\Core\Testing\GoldenFiles;

/**
 * The selectors written down say what the selectors compiled in said: step 3.6.
 *
 * `Joomla6Selectors` is four closures reaching into ER1's shape by name, and
 * `Joomla6Selectors::PATHS` is the same four as data. Swapping one for the other
 * is the whole of 3.6 in this component, and the only thing that makes the swap
 * safe is that the two agree - over real projects, not over a shape invented
 * here to make them agree.
 *
 * So this asks the question of the golden models, which are the three projects
 * every generated file in `Fixtures/golden/expected` came out of. If a path is
 * wrong, `GoldenOutputTest` says so too, in three thousand lines of diff; this
 * says so in one assertion naming the selector.
 *
 * @since  1.2.0
 */
final class Joomla6SelectorTest extends TestCase
{
    private function fixtures(): GoldenFiles
    {
        return new GoldenFiles(\dirname(__DIR__) . '/Fixtures/golden');
    }

    /**
     * @return  object[]  The golden projects, keyed by name.
     */
    private function projects(): array
    {
        $projects = [];

        foreach ($this->fixtures()->names() as $name) {
            $projects[$name] = json_decode($this->fixtures()->model($name), false, 512, JSON_THROW_ON_ERROR);
        }

        return $projects;
    }

    /**
     * Both halves name the same selectors, and neither has one the other lacks.
     *
     * The fallback in `RuleDrivenGenerator` is per-vocabulary and not
     * per-selector: either the paths are used or the closures are. A path
     * missing from this list would therefore not fall back to its closure, it
     * would be a selector that no longer exists - and a rule naming it fails at
     * the point of generating rather than at the point of being written.
     */
    public function testTheClosuresAndThePathsNameTheSameSelectors(): void
    {
        $registered = Joomla6Selectors::registry()->all();
        $described  = array_keys(Joomla6Selectors::PATHS);

        sort($registered);
        sort($described);

        $this->assertSame($registered, $described);

        // Not vacuously: four is the list the class docblock claims.
        $this->assertCount(4, $described);
    }

    /**
     * Over a real project, a path yields exactly what its closure yielded.
     *
     * Identity rather than equality, because a selector hands the rule engine
     * the node out of the model - not a copy of it - and order is part of the
     * answer: the first back-end index page becomes the component's default
     * view, so a path that returned the same pages in page order rather than in
     * reference order would generate a different component.
     */
    public function testEachPathYieldsWhatItsClosureYields(): void
    {
        $closures = Joomla6Selectors::registry();
        $paths    = DataSelectors::registry(Joomla6Selectors::PATHS, ReferenceIndex::fromTable(Er1::TABLE));

        $joined = 0;

        foreach ($this->projects() as $name => $project) {
            foreach (array_keys(Joomla6Selectors::PATHS) as $selector) {
                $this->assertSame(
                    array_values($closures->call($selector, [$project], 'test')),
                    array_values($paths->call($selector, [$project], 'test')),
                    $selector . ' over ' . $name . ' is not the same set of nodes.'
                );
            }

            $joined += \count($closures->call('backendPages', [$project], 'test'));
        }

        // The guard the rest of this rests on. Two empty arrays are the same
        // two empty arrays, and the join - the one selector a dotted string
        // could not have expressed - is the one that would be empty if the
        // `follow` step reached nothing.
        $this->assertGreaterThan(0, $joined, 'No golden project has a back-end page to join to.');
    }

    /**
     * The published vocabulary carries them, which is how anything else sees them.
     *
     * `VocabularyTest` says the committed file is what the script writes. This
     * says what the script writes is these, so that the file being up to date
     * and the file being right are not the same statement made twice.
     */
    public function testThePublishedVocabularyCarriesThePaths(): void
    {
        $vocabulary = Vocabulary::fromFile(
            \dirname(__DIR__, 2)
            . '/src/administrator/components/com_extengen/src/Generator/Rules/joomla6.vocabulary.json'
        );

        $this->assertSame(
            json_decode(json_encode(Joomla6Selectors::PATHS, JSON_THROW_ON_ERROR), true),
            $vocabulary->paths()
        );

        foreach (array_keys(Joomla6Selectors::PATHS) as $selector) {
            $this->assertTrue($vocabulary->describes($selector), $selector . ' is named but not described.');
        }
    }
}
