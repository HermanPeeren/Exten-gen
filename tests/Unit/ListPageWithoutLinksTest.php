<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Gen\Core\Output\FileCollection;

/**
 * A back-end list page without links still opens its form: step 6.6.
 *
 * Found rehearsing the JWC demo. BalloonLedger with a Period entity added, a
 * list page "Periods" and a details page "Period", neither with a link: New on
 * the Periods list pressed `.add`, the dispatcher looked for a controller with
 * no name, and the list answered *404*. The link derivation returned `''` for
 * a back-end page that links nowhere, and that `''` went into the toolbar, the
 * list's edit links and the form controller's `view_list`.
 *
 * The model here is exactly that one, built on the LedgerLang fixture and
 * generated with the LedgerLang generator, as the demo was.
 */
final class ListPageWithoutLinksTest extends TestCase
{
    private const PERIOD_ENTITY = 'a1c0ffee-0000-4000-8000-000000000001';
    private const PERIODS_PAGE  = 'a1c0ffee-0000-4000-8000-000000000002';
    private const PERIOD_PAGE   = 'a1c0ffee-0000-4000-8000-000000000003';
    private const ADMIN         = 'administrator/components/com_balloonledger/';

    /**
     * BalloonLedger plus Period, its two pages in the back end, no links on either.
     *
     * The new groups are keyed `datamodel90`, `pages90` and so on, the way a
     * Joomla subform stores them: a key without the number is not found by
     * the walk that follows a page reference, and the pages silently vanish.
     */
    private function model(bool $withDetailsPage = true): object
    {
        $model = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden-joomla6-ledgerlang/models/balloonledger.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        );

        $model->datamodel->datamodel90 = (object) [
            'entity_name' => 'Period',
            'field'       => (object) [
                'field0' => (object) [
                    'field_name' => 'name',
                    'field_type' => 'Property',
                    'property'   => (object) ['type' => 'Short_Text', 'default_ref_display' => '1'],
                    'field_id'   => 'a1c0ffee-0000-4000-8000-000000000004',
                ],
            ],
            'entity_id' => self::PERIOD_ENTITY,
        ];

        $page = static fn (string $id, string $name, string $type): object => (object) [
            'page_name'           => $name,
            'page_type'           => $type,
            'entity_ref'          => (object) ['entity_ref0' => (object) ['reference' => self::PERIOD_ENTITY]],
            'filters'             => [],
            'presentationcolumns' => [],
            'editfields'          => [],
            'page_id'             => $id,
        ];

        $model->pages->pages90 = $page(self::PERIODS_PAGE, 'Periods', 'indexpage');
        $model->extensions->component->Sections->backendsection->backendsection90
            = (object) ['page_reference' => self::PERIODS_PAGE];

        if ($withDetailsPage) {
            $model->pages->pages91 = $page(self::PERIOD_PAGE, 'Period', 'detailspage');
            $model->extensions->component->Sections->backendsection->backendsection91
                = (object) ['page_reference' => self::PERIOD_PAGE];
        }

        return $model;
    }

    private function generate(object $model): FileCollection
    {
        return GenerationHarness::runWithGenerator($model, 'joomla6-ledgerlang');
    }

    public function testNewOnTheListOpensTheDetailsPage(): void
    {
        $files = $this->generate($this->model());
        $view  = $files->get(self::ADMIN . 'src/View/Periods/HtmlView.php');

        $this->assertStringContainsString("addNew('period.add')", $view);
        $this->assertStringNotContainsString("addNew('.add')", $view);
    }

    public function testTheListsEditLinksNameTheDetailsPage(): void
    {
        $list = $this->generate($this->model())->get(self::ADMIN . 'tmpl/periods/default.php');

        $this->assertStringContainsString('task=period.edit&id=', $list);
        $this->assertStringNotContainsString('task=.edit', $list);
    }

    public function testTheFormControllerReturnsToTheList(): void
    {
        $controller = $this->generate($this->model())->get(self::ADMIN . 'src/Controller/PeriodController.php');

        $this->assertStringContainsString("\$this->view_list = 'periods';", $controller);
        $this->assertStringContainsString('&view=periods\'', $controller);
    }

    public function testAListWithoutADetailsPageFallsBackToTheEntityName(): void
    {
        $view = $this->generate($this->model(false))->get(self::ADMIN . 'src/View/Periods/HtmlView.php');

        $this->assertStringContainsString("addNew('period.add')", $view);
    }

    public function testNoGeneratedBackEndNamesAControllerWithoutAName(): void
    {
        foreach ($this->generate($this->model())->all() as $path => $contents) {
            if (!str_starts_with($path, self::ADMIN)) {
                continue;
            }

            $this->assertStringNotContainsString("addNew('.add')", $contents, $path);
            $this->assertStringNotContainsString('task=.edit', $contents, $path);
            $this->assertStringNotContainsString("view_list = ''", $contents, $path);
        }
    }
}
