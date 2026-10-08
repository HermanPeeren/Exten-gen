<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Model\ProjectValidator;
use Yepr\Gen\Core\Model\ValidationException;

/**
 * Custom code is refused when its slot does not belong to the object it is on.
 *
 * A page's form offers every page slot - its type is chosen in the same form,
 * so the dropdown cannot know it - and an entity's form offers the entity's.
 * Code a page cannot have is code the generator would never write, without a
 * word, so the validator says so before anything is generated.
 *
 * @since  1.3.5
 */
final class SlotPlacementTest extends TestCase
{
    /**
     * The BalloonPlanning model, with one change made to its Flights list page.
     */
    private function withFlightsSlot(string $slot): Project
    {
        $model = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/balloonplanning.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        );

        foreach ($model->pages as $page) {
            if ($page->page_name === 'Flights') {
                $page->customcode->customcode0->slot = $slot;
            }
        }

        return Project::fromObject($model);
    }

    private function problems(Project $project): array
    {
        try {
            (new ProjectValidator())->assertValid($project);
        } catch (ValidationException $e) {
            return $e->getErrors();
        }

        return [];
    }

    public function testTheGoldenModelsSlotsAreWhereTheyBelong(): void
    {
        // Flights is a list page with list-page slots: the vacuity guard.
        $this->assertSame([], $this->problems($this->withFlightsSlot('site.index.layout')));
    }

    public function testADetailsPageSlotOnAListPageIsRefused(): void
    {
        $this->assertSame(
            ['page "Flights" has custom code for the slot "site.details.layout", which is not a slot of a list page'],
            $this->problems($this->withFlightsSlot('site.details.layout'))
        );
    }

    public function testAnEntitySlotOnAPageIsRefused(): void
    {
        $this->assertSame(
            ['page "Flights" has custom code for the slot "table.check", which is not a slot of a list page'],
            $this->problems($this->withFlightsSlot('table.check'))
        );
    }

    public function testASlotTheCatalogueDoesNotKnowIsLeftToTheGenerator(): void
    {
        // An old model meeting a newer catalogue: reported in the log, not refused.
        $this->assertSame([], $this->problems($this->withFlightsSlot('no.such.slot')));
    }
}
