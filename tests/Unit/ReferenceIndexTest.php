<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Reference\ReferenceIndex;

/**
 * The index says the same thing the three field classes used to say.
 *
 * `EntityReferenceField`, `FieldReferenceField` and `PageReferenceField` each
 * walked the project themselves. Those walks are the specification this
 * replaces, so the tests are written against the models that were stored while
 * they were in use, and check the answers a person would have seen in a
 * dropdown.
 */
final class ReferenceIndexTest extends TestCase
{
    /** @return array<string, string[]> */
    public static function storedModels(): array
    {
        $models = [];

        foreach (glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: [] as $path) {
            $name          = basename($path, '.json');
            $models[$name] = [$name];
        }

        return $models;
    }

    #[DataProvider('storedModels')]
    public function testEveryEntityFieldAndPageInTheModelIsThere(string $name): void
    {
        $model = $this->model($name);
        $index = (new ReferenceIndex())->forProject($model);

        // Counted from the model directly rather than from a number written
        // here, so the rule survives somebody editing a fixture.
        $entities = (array) $model->datamodel;
        $pages    = (array) $model->pages;
        $fields   = 0;

        foreach ($entities as $entity) {
            $fields += \count((array) ($entity->field ?? []));
        }

        $this->assertCount(\count($entities), $index['Entity']);
        $this->assertCount(\count($pages), $index['Page']);
        $this->assertCount($fields, $index['Field']);
    }

    #[DataProvider('storedModels')]
    public function testEveryEntryHasAnIdAndAName(string $name): void
    {
        foreach ((new ReferenceIndex())->forProject($this->model($name)) as $type => $entries) {
            foreach ($entries as $entry) {
                $this->assertNotSame('', $entry['id'], $type . ' has an entry with no id');
                $this->assertNotSame('', $entry['name'], $type . ' has an entry with no name: ' . $entry['id']);
            }
        }
    }

    /**
     * A field belongs to exactly one entity, and says which.
     *
     * This is what a fields dropdown is scoped by: asked for the fields of one
     * entity, it filters on this. A field whose parent is not an entity in the
     * same index would show up in nobody's list.
     */
    #[DataProvider('storedModels')]
    public function testEveryFieldPointsAtAnEntityThatIsThere(string $name): void
    {
        $index      = (new ReferenceIndex())->forProject($this->model($name));
        $entityIds  = array_column($index['Entity'], 'id');
        $orphans    = [];

        foreach ($index['Field'] as $field) {
            if (!\in_array($field['parent'], $entityIds, true)) {
                $orphans[] = $field['name'] . ' -> ' . $field['parent'];
            }
        }

        $this->assertSame([], $orphans, 'Fields pointing at no entity: ' . implode(', ', $orphans));
    }

    /**
     * The answer for a real question, spelled out.
     *
     * The counted tests above would pass on an index that returned the right
     * number of wrong things, so one case is written by hand from the fixture.
     */
    public function testTheConferenceEntitiesAreNamedAsAPersonWouldSeeThem(): void
    {
        $index = (new ReferenceIndex())->forProject($this->model('conference'));

        $this->assertSame(
            ['Program', 'Room', 'Speaker', 'Talk'],
            $this->sortedNames($index['Entity'])
        );
    }

    public function testAProjectThatWasNeverSavedIndexesToEmptyListsRatherThanNothing(): void
    {
        // The edit form for a new project renders the same reference fields.
        // They need an empty list to work with, not a missing key.
        $index = (new ReferenceIndex())->forProject(null);

        $this->assertSame(['Entity', 'Page', 'Field'], array_keys($index));
        $this->assertSame([[], [], []], array_values($index));
    }

    /**
     * An object with no id is left out.
     *
     * The client gives a newly added entity its uuid; until then there is
     * nothing to point at, and an entry with an empty id would put a blank
     * choice in every dropdown.
     */
    public function testAnObjectWithNoIdYetIsNotOffered(): void
    {
        $project = json_decode((string) json_encode([
            'datamodel' => [
                'datamodel0' => ['entity_id' => '', 'entity_name' => 'Half typed'],
                'datamodel1' => ['entity_id' => 'abc', 'entity_name' => 'Saved'],
            ],
            'pages' => [],
        ]), false, 512, JSON_THROW_ON_ERROR);

        $index = (new ReferenceIndex())->forProject($project);

        $this->assertSame([['id' => 'abc', 'name' => 'Saved']], $index['Entity']);
    }

    /**
     * @param  list<array{id: string, name: string, parent?: string}>  $entries
     *
     * @return string[]
     */
    private function sortedNames(array $entries): array
    {
        $names = array_column($entries, 'name');

        sort($names);

        return $names;
    }

    private function model(string $name): object
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json'),
            false,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
