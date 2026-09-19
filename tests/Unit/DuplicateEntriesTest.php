<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Model\ProjectValidator;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Gen\Core\Model\ValidationException;

/**
 * A repeating group must not name the same thing twice.
 *
 * Every duplicate means the same file, table or column produced twice, with the
 * second one winning. That used to be invisible: the generator opened each file
 * with `fopen(..., 'w')`, and a second write to the same path is just a write.
 *
 * It surfaced at 1.4, when generation started going into a collection that
 * refuses a path it already holds — and then the counter found a second
 * instance nobody had suspected. Both came from real stored models:
 *
 * - `EventSchedule` listed `Tracks` twice among its back-end pages, which put a
 *   duplicate entry in the component's submenu.
 * - `MyConference` listed `en-GB` twice among its languages, which wrote every
 *   en-GB file twice.
 *
 * Those two models are kept in `tests/Fixtures/invalid/`, exactly as they were
 * stored, so the rule is tested against the data that motivated it rather than
 * against something written to pass.
 */
final class DuplicateEntriesTest extends TestCase
{
    /**
     * @param  string  $fixture   The invalid model.
     * @param  string  $expected  The message a person has to be able to act on.
     */
    #[DataProvider('storedModelsWithADuplicate')]
    public function testARealStoredModelWithADuplicateIsRefused(string $fixture, string $expected): void
    {
        $json = (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/invalid/' . $fixture . '.json');

        try {
            (new ProjectValidator())->assertValid(Project::fromJson($json));
            $this->fail('A model with a duplicated entry should not validate.');
        } catch (ValidationException $e) {
            $this->assertContains($expected, $e->getErrors());
        }
    }

    /** @return array<string, string[]> */
    public static function storedModelsWithADuplicate(): array
    {
        return [
            'a page listed twice in a section' => [
                'duplicate-page-reference',
                'the back-end page list names "Tracks" 2 times; each entry has to be distinct',
            ],
            'a language listed twice' => [
                'duplicate-language',
                'the language list names "en-GB" 2 times; each entry has to be distinct',
            ],
        ];
    }

    /**
     * The message names the page, not the reference.
     *
     * A section stores a uuid. Reported as one it tells the reader nothing they
     * can act on, and the whole value of the rule is that somebody can go and
     * fix the model.
     */
    public function testASectionDuplicateNamesThePageRatherThanItsId(): void
    {
        $json = (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/invalid/duplicate-page-reference.json');

        try {
            (new ProjectValidator())->assertValid(Project::fromJson($json));
            $this->fail('A model with a duplicated entry should not validate.');
        } catch (ValidationException $e) {
            $message = implode(' ', $e->getErrors());

            $this->assertStringContainsString('Tracks', $message);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{8}-[0-9a-f]{4}-/', $message);
        }
    }

    /**
     * Case does not make two things different.
     *
     * Two entities called Flight and flight are one table, so the rule compares
     * without case — and reports the spelling that was typed, because that is
     * what somebody will search the form for.
     */
    public function testTwoNamesDifferingOnlyInCaseAreADuplicate(): void
    {
        $project = Project::fromJson(json_encode([
            'extensions' => ['component' => ['component_name' => 'X']],
            'datamodel'  => [
                'datamodel0' => ['entity_name' => 'Flight'],
                'datamodel1' => ['entity_name' => 'flight'],
            ],
            'pages' => ['pages0' => ['page_name' => 'Flights']],
        ], JSON_THROW_ON_ERROR));

        try {
            (new ProjectValidator())->assertValid($project);
            $this->fail('Flight and flight are one table.');
        } catch (ValidationException $e) {
            $this->assertContains(
                'the entity list names "Flight" 2 times; each entry has to be distinct',
                $e->getErrors()
            );
        }
    }

    /**
     * The same field name in two different entities is two different columns.
     *
     * A rule that cannot tell those apart would refuse almost every real model,
     * since `name` and `id` appear everywhere.
     */
    public function testTheSameFieldNameInTwoEntitiesIsFine(): void
    {
        $this->expectNotToPerformAssertions();

        $project = Project::fromJson(json_encode([
            'extensions' => ['component' => ['component_name' => 'X']],
            'datamodel'  => [
                'datamodel0' => ['entity_name' => 'Flight', 'field' => ['field0' => ['field_name' => 'name']]],
                'datamodel1' => ['entity_name' => 'Balloon', 'field' => ['field0' => ['field_name' => 'name']]],
            ],
            'pages' => ['pages0' => ['page_name' => 'Flights']],
        ], JSON_THROW_ON_ERROR));

        (new ProjectValidator())->assertValid($project);
    }

    public function testTwoFieldsWithOneNameInOneEntityIsNot(): void
    {
        $project = Project::fromJson(json_encode([
            'extensions' => ['component' => ['component_name' => 'X']],
            'datamodel'  => [
                'datamodel0' => [
                    'entity_name' => 'Flight',
                    'field'       => [
                        'field0' => ['field_name' => 'departure'],
                        'field1' => ['field_name' => 'departure'],
                    ],
                ],
            ],
            'pages' => ['pages0' => ['page_name' => 'Flights']],
        ], JSON_THROW_ON_ERROR));

        try {
            (new ProjectValidator())->assertValid($project);
            $this->fail('One entity cannot have the same column twice.');
        } catch (ValidationException $e) {
            $this->assertContains('entity "Flight" names the field "departure" 2 times', $e->getErrors());
        }
    }

    /**
     * And the models the suite generates from are clean, both ways: the
     * validator accepts them, and nothing is written twice when they run.
     */
    #[DataProvider('goldenModels')]
    public function testEveryGoldenModelIsValidAndCollisionFree(string $name): void
    {
        $json = (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json');

        (new ProjectValidator())->assertValid(Project::fromJson($json));

        $this->assertSame(
            [],
            GenerationHarness::collisions(json_decode($json, false, 512, JSON_THROW_ON_ERROR)),
            $name . ' writes a file more than once'
        );
    }

    /** @return array<string, string[]> */
    public static function goldenModels(): array
    {
        $models = [];

        foreach (glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: [] as $path) {
            $name          = basename($path, '.json');
            $models[$name] = [$name];
        }

        return $models;
    }
}
