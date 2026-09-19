<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Model\ProjectValidator;
use Yepr\Gen\Core\Model\ValidationException;

final class ProjectTest extends TestCase
{
    private function fixture(string $name = 'balloonplanning'): string
    {
        return (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json');
    }

    public function testItReadsARealStoredProject(): void
    {
        $project = Project::fromJson($this->fixture());

        $this->assertSame('BalloonPlanning', $project->name());
        $this->assertSame('BalloonPlanning', $project->componentName());
        $this->assertNotEmpty($project->entities());
        $this->assertNotEmpty($project->pages());
    }

    /**
     * Joomla subforms store a repeating group as an object keyed `datamodel0`,
     * `datamodel1` and so on. Every generator walks that numbering by hand
     * today; the model hands back a list so that they will not have to.
     */
    public function testSubformGroupsComeBackAsLists(): void
    {
        $project = Project::fromJson($this->fixture());

        $this->assertArrayHasKey(0, $project->entities());
        $this->assertSame(range(0, \count($project->entities()) - 1), array_keys($project->entities()));

        foreach ($project->entities() as $entity) {
            $this->assertIsObject($entity);
            $this->assertNotSame('', (string) ($entity->entity_name ?? ''));
        }
    }

    public function testAStoredProjectWithNoVersionReadsAsTheFirstOne(): void
    {
        // Everything saved before this step carries no version, and 1.0 is what
        // those models are rather than a guess about them.
        $this->assertSame('1.0', Project::fromJson($this->fixture())->modelVersion());
        $this->assertSame(Project::CURRENT_VERSION, Project::fromJson($this->fixture())->modelVersion());
    }

    public function testAStoredVersionIsKept(): void
    {
        $project = Project::fromJson('{"modelVersion":"1.1","name":"X"}');

        $this->assertSame('1.1', $project->modelVersion());
    }

    public function testFromArrayTakesFormDataAsPosted(): void
    {
        $project = Project::fromArray([
            'name'       => 'Flight plan',
            'extensions' => ['component' => ['component_name' => 'Flights']],
        ]);

        $this->assertSame('Flight plan', $project->name());
        $this->assertSame('Flights', $project->componentName());
    }

    public function testSomethingThatIsNotAProjectIsRefused(): void
    {
        $this->expectException(\JsonException::class);
        Project::fromJson('{not json');
    }

    public function testJsonThatIsNotAnObjectIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Project::fromJson('[1, 2, 3]');
    }

    /**
     * Asking a project that is missing a part should answer emptily rather than
     * explode: reference fields render while a model is still half-written.
     */
    public function testAnIncompleteProjectAnswersEmptyRatherThanFailing(): void
    {
        $project = Project::fromJson('{}');

        $this->assertSame('', $project->name());
        $this->assertSame('', $project->componentName());
        $this->assertSame([], $project->entities());
        $this->assertSame([], $project->pages());
        $this->assertSame([], $project->languages());
    }

    public function testARealProjectValidates(): void
    {
        $this->expectNotToPerformAssertions();

        (new ProjectValidator())->assertValid(Project::fromJson($this->fixture()));
    }

    /**
     * @param  string    $json      A project that should be refused.
     * @param  string[]  $expected  Fragments that must appear among the problems.
     */
    #[DataProvider('invalidProjects')]
    public function testAnIncompleteProjectIsRefusedWithEveryProblemAtOnce(string $json, array $expected): void
    {
        try {
            (new ProjectValidator())->assertValid(Project::fromJson($json));
            $this->fail('An incomplete project should not validate.');
        } catch (ValidationException $e) {
            $errors = implode(' | ', $e->getErrors());

            foreach ($expected as $fragment) {
                $this->assertStringContainsString($fragment, $errors);
            }
        }
    }

    /** @return array<string, array{string, string[]}> */
    public static function invalidProjects(): array
    {
        return [
            'empty' => [
                '{}',
                ['no component name', 'no entities', 'no pages'],
            ],
            'a future format' => [
                '{"modelVersion":"9.9"}',
                ['format version 9.9'],
            ],
            'an unnamed entity' => [
                '{"extensions":{"component":{"component_name":"X"}},'
                . '"datamodel":{"datamodel0":{"entity_name":""}},'
                . '"pages":{"pages0":{"page_name":"List"}}}',
                ['entity 1 has no name'],
            ],
        ];
    }

    /**
     * All of them, not just the first: a user fixing a model one error per run
     * is a user who gives up.
     */
    public function testEveryProblemIsReportedTogether(): void
    {
        try {
            (new ProjectValidator())->assertValid(Project::fromJson('{}'));
            $this->fail('An empty project should not validate.');
        } catch (ValidationException $e) {
            $this->assertGreaterThanOrEqual(3, \count($e->getErrors()));
        }
    }
}
