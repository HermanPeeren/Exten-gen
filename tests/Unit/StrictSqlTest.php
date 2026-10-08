<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A generated component's schema installs on MySQL 8 in strict mode.
 *
 * The Joomla 6 target wrote a DateTime property as
 * `datetime NOT NULL DEFAULT '0000-00-00 00:00:00'`, which MySQL 8 refuses
 * under its default `sql_mode` (`NO_ZERO_DATE` with strict mode), so a
 * component with a date in it did not install there. Joomla's own tables
 * dropped the zero date in 4.0; a nullable date is `DEFAULT NULL` now, and the
 * generated Table turns an empty date from the form into NULL, because ''
 * is refused in a date column for the same reason.
 *
 * The golden files pin the exact output. This states the rule, over every
 * approved Joomla schema, so that a template change reintroducing a zero date
 * fails with a sentence rather than with a diff.
 *
 * @since  1.3.1
 */
final class StrictSqlTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function schemas(): array
    {
        $cases = [];

        foreach (glob(\dirname(__DIR__) . '/Fixtures/golden/expected/*/administrator/components/*/sql/install.mysql.utf8.sql') ?: [] as $path) {
            $cases[basename(\dirname($path, 5))] = [$path];
        }

        return $cases;
    }

    #[DataProvider('schemas')]
    public function testNoColumnDefaultsToAZeroDate(string $path): void
    {
        $this->assertStringNotContainsString('0000-00-00', (string) file_get_contents($path));
    }

    public function testThereAreSchemasToCheck(): void
    {
        $this->assertGreaterThanOrEqual(4, \count(self::schemas()));
    }

    public function testTheTableTurnsAnEmptyDateIntoNull(): void
    {
        $template = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/generator_templates/Joomla6/component/'
            . 'administrator/components/com_componentname/src/Table/Table.php.twig'
        );

        $this->assertStringContainsString("preg_match('/^(date|datetime|time|timestamp)\\b/i'", $template);
        $this->assertStringContainsString('public function store($updateNulls = true)', $template);
    }
}
