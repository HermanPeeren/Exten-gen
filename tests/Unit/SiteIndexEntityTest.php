<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;

/**
 * A front-end list reads its own entity's fields, whatever its filters name: step 6.8.
 *
 * The imperative front-end generator reused `$entity` as the variable of its
 * filter loop, so by the time it built the list's columns and joins the name
 * held the *last filter's* entity. Step 1.4 reproduced that on purpose,
 * because its claim was byte-identical output, and wrote it down as preserved
 * rather than fixed. No golden fixture has a filter on another entity, which
 * is how it stayed hidden: this test gives the Accounts list one, on an entry
 * line's debit. An entry line refers to four entities, so the wrong entity
 * shows twice: in the selected columns, and in four joins on columns Account
 * does not have.
 *
 * The filter itself still filters on `account.debit`, which does not exist:
 * a filter on another entity's field has no join path in the model. That is
 * a separate defect, left open in 6.8.
 */
final class SiteIndexEntityTest extends TestCase
{
    private const LINE_ENTITY = 'af50be2e-55bf-45d0-a37b-77f66f5de57e';
    private const LINE_DEBIT  = '481d9e6c';

    private function generatedListModel(): string
    {
        $model = json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden-joomla6-ledgerlang/models/balloonledger.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        );

        $debit = null;

        foreach ($model->datamodel as $entity) {
            foreach ($entity->field as $field) {
                if (str_starts_with($field->field_id, self::LINE_DEBIT)) {
                    $debit = $field->field_id;
                }
            }
        }

        $this->assertNotNull($debit, 'The fixture no longer has the entry line debit this test filters on.');

        foreach ($model->pages as $page) {
            if ($page->page_name === 'Accounts') {
                $page->filters = (object) [
                    'filters0' => (object) ['entity_reference' => self::LINE_ENTITY, 'field_reference' => $debit],
                ];
            }
        }

        return GenerationHarness::runWithGenerator($model, 'joomla6-ledgerlang')
            ->get('components/com_balloonledger/src/Model/AccountsModel.php');
    }

    /**
     * The `select([...])` block: the list's own columns.
     */
    private function selected(string $list): string
    {
        $this->assertSame(1, preg_match('/\$query->select\(\[(.*?)\]\);/s', $list, $match), 'No select block found.');

        return $match[1];
    }

    public function testTheListSelectsThePagesOwnColumns(): void
    {
        $selected = $this->selected($this->generatedListModel());

        foreach (['code', 'name', 'nature', 'normal_balance', 'postable', 'reconcilable', 'active', 'id'] as $column) {
            $this->assertStringContainsString("'account." . $column . "'", $selected);
        }

        $this->assertStringNotContainsString("'account.debit'", $selected);
        $this->assertStringNotContainsString("'account.description'", $selected);
    }

    public function testTheListJoinsNothingAccountDoesNotReferTo(): void
    {
        $this->assertStringNotContainsString('->join(', $this->generatedListModel());
    }
}
