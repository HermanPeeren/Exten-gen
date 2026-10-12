<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generator\Target\Targets;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Pipeline;

/**
 * What ER1 says about a field's values reaches the generated extension: step 6.7.
 *
 * ER1 has had `isunique` and `isprimary` on a property and `isrequired` on a
 * reference since the beginning, and no generator read any of them. An
 * account code could be entered twice and left empty, and the chart of
 * accounts in the talk demo depended on it being neither.
 *
 * Built on the LedgerLang fixture, whose Account, Journal and TaxCode each
 * mark `code` unique and primary, and whose journal entry requires its
 * journal - and on eventschedule, whose presentation type keys a Text column.
 */
final class RequiredUniqueTest extends TestCase
{
    private const ADMIN = 'administrator/components/com_balloonledger/';

    private function ledger(): FileCollection
    {
        return GenerationHarness::runWithGenerator($this->model('golden-joomla6-ledgerlang', 'balloonledger'), 'joomla6-ledgerlang');
    }

    private function model(string $fixtures, string $name): object
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/' . $fixtures . '/models/' . $name . '.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    private function otherTarget(string $targetId, object $model): FileCollection
    {
        if (!\defined('JPATH_ROOT')) {
            \define('JPATH_ROOT', \dirname(__DIR__, 2) . '/src');
        }

        return (new Pipeline())->run(
            Project::fromObject($model),
            Targets::registry(\dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/generator_templates')->get($targetId)
        );
    }

    public function testAUniquePropertyIsKeyedUniqueInTheInstallSql(): void
    {
        $sql = $this->ledger()->get(self::ADMIN . 'sql/install.mysql.utf8.sql');

        $this->assertMatchesRegularExpression(
            '/CREATE TABLE IF NOT EXISTS `#__balloonledger_account` \([^;]*UNIQUE KEY `idx_code` \(`code`\)/',
            $sql
        );
        $this->assertSame(3, substr_count($sql, 'UNIQUE KEY `idx_code`'), 'Account, Journal and TaxCode each key their code.');
    }

    public function testATextColumnIsKeyedOnAPrefix(): void
    {
        $sql = GenerationHarness::run($this->model('golden', 'eventschedule'))
            ->get('administrator/components/com_eventschedule/sql/install.mysql.utf8.sql');

        $this->assertStringContainsString('UNIQUE KEY `idx_presentation_type_name` (`presentation_type_name`(255))', $sql);
    }

    public function testRequiredFieldsAreRequiredOnTheForm(): void
    {
        $files = $this->ledger();

        // A primary property.
        $this->assertMatchesRegularExpression(
            '/<field name="code"[^>]* required="true"/',
            $files->get(self::ADMIN . 'forms/account.xml')
        );

        // A reference marked isrequired.
        $this->assertMatchesRegularExpression(
            '/<field name="journal" type="sql"[^>]* required="true"/',
            $files->get(self::ADMIN . 'forms/journalentry.xml')
        );

        // And nothing else.
        $this->assertSame(1, substr_count($files->get(self::ADMIN . 'forms/account.xml'), 'required="true"'));
    }

    public function testOnlyADateTimeCarriesADateFormat(): void
    {
        foreach ($this->ledger()->all() as $path => $contents) {
            if (!str_contains($path, '/forms/')) {
                continue;
            }

            preg_match_all('/<field [^>]*format="[^"]*"[^>]*>/', $contents, $matches);

            foreach ($matches[0] as $field) {
                $this->assertStringContainsString('type="calendar"', $field, $path . ': ' . $field);
            }
        }

        $session = GenerationHarness::run($this->model('golden', 'conference'))
            ->get('administrator/components/com_conference/forms/session.xml');

        $this->assertMatchesRegularExpression('/<field name="time" type="calendar" format="%Y-%m-%d %H:%M" showtime="true"/', $session);
        $this->assertStringNotContainsString('<field name="title" type="text" format=', $session);
    }

    public function testTheTableRefusesADuplicateWithATranslatableMessage(): void
    {
        $files = $this->ledger();
        $table = $files->get(self::ADMIN . 'src/Table/AccountTable.php');

        $this->assertStringContainsString("'code' => [", $table);
        $this->assertStringContainsString("'COM_BALLOONLEDGER_ACCOUNT_CODE_REQUIRED',", $table);
        $this->assertStringContainsString("'COM_BALLOONLEDGER_ACCOUNT_CODE_NOT_UNIQUE',", $table);
        $this->assertStringContainsString('private function isUnique(string $column): bool', $table);

        // In both language files, because the site saves through this Table too.
        foreach (['administrator/components/com_balloonledger/', 'components/com_balloonledger/'] as $root) {
            $ini = $files->get($root . 'language/en-GB/com_balloonledger.ini');

            $this->assertStringContainsString('COM_BALLOONLEDGER_ACCOUNT_CODE_NOT_UNIQUE="Another account already has code %s."', $ini);
            $this->assertStringContainsString('COM_BALLOONLEDGER_ACCOUNT_CODE_REQUIRED="The account needs a code."', $ini);
        }
    }

    public function testAnEntityWithNothingUniqueGetsNoCheck(): void
    {
        $table = $this->ledger()->get(self::ADMIN . 'src/Table/PartyTable.php');

        $this->assertStringNotContainsString('isUnique', $table);
    }

    public function testWordPressAndDrupalKeyTheColumnUnique(): void
    {
        $model = $this->model('golden-joomla6-ledgerlang', 'balloonledger');

        $activator = '';

        foreach ($this->otherTarget('wordpress', $model)->all() as $path => $contents) {
            if (str_ends_with($path, '-activator.php')) {
                $activator = $contents;
            }
        }

        $this->assertMatchesRegularExpression('/PRIMARY KEY  \(id\),\s+UNIQUE KEY code \(code\)\s+\)/', $activator);

        $install = '';

        foreach ($this->otherTarget('drupal', $model)->all() as $path => $contents) {
            if (str_ends_with($path, '.install')) {
                $install = $contents;
            }
        }

        $this->assertStringContainsString("'unique keys' => [\n      'code' => ['code'],\n    ],", $install);
    }

    public function testAnUntickedFlagFromLionWebIsNotTicked(): void
    {
        $property = (object) ['type' => 'Short_Text', 'isunique' => 'false', 'isprimary' => '0'];
        $field    = (object) ['field_name' => 'x', 'field_type' => 'Property', 'property' => $property];

        $this->assertFalse(FieldKind::isUnique($field));
        $this->assertFalse(FieldKind::isRequired($field));

        $property->isunique = '1';

        $this->assertTrue(FieldKind::isUnique($field));
        $this->assertFalse(FieldKind::isRequired($field), 'Unique is not required: an empty value is no value.');
    }
}
