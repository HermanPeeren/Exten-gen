<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Tests\Support\GenerationHarness;

/**
 * What this generator writes is valid PHP, XML, INI and SQL.
 *
 * The golden comparison says the output has not changed. It does not say the
 * output is loadable: a template edit that produces the same bytes for three
 * fixtures and broken syntax for a fourth would pass it, and so would a change
 * that broke every file at once - the baseline would simply be recaptured
 * broken, because a diff of one wrong file against another wrong file reads
 * like a diff.
 *
 * So this asks the parsers. PHP through `token_get_all` with `TOKEN_PARSE`,
 * which raises `ParseError` on bad syntax and costs nothing, rather than a
 * subprocess per file. XML and INI through the readers Joomla itself uses, for
 * the same reason a manifest that will not parse is not a manifest.
 *
 * It generates rather than reading the committed fixtures, so it fails on the
 * run that broke something rather than on the commit that recorded it.
 */
final class GeneratedSyntaxTest extends TestCase
{
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

    #[DataProvider('goldenModels')]
    public function testEveryGeneratedPhpFileParses(string $name): void
    {
        $broken  = [];
        $checked = 0;

        foreach ($this->generate($name) as $path => $contents) {
            if (!str_ends_with($path, '.php')) {
                continue;
            }

            $checked++;

            try {
                // For the ParseError, not the tokens: TOKEN_PARSE makes
                // token_get_all raise on bad syntax, which is `php -l` without
                // a subprocess per file.
                $this->assertNotSame([], token_get_all($contents, TOKEN_PARSE));
            } catch (\ParseError $e) {
                $broken[] = $path . ': ' . $e->getMessage() . ' on line ' . $e->getLine();
            }
        }

        $this->assertGreaterThan(0, $checked, $name . ' generated no PHP at all');
        $this->assertSame([], $broken, "Generated PHP that will not parse:\n" . implode("\n", $broken));
    }

    #[DataProvider('goldenModels')]
    public function testEveryGeneratedXmlFileParses(string $name): void
    {
        $broken  = [];
        $checked = 0;

        foreach ($this->generate($name) as $path => $contents) {
            if (!str_ends_with($path, '.xml')) {
                continue;
            }

            $checked++;

            $previous = libxml_use_internal_errors(true);

            libxml_clear_errors();

            if (simplexml_load_string($contents) === false) {
                $first    = libxml_get_errors()[0] ?? null;
                $broken[] = $path . ': ' . ($first === null ? 'not valid XML' : trim($first->message));
            }

            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $this->assertGreaterThan(0, $checked, $name . ' generated no XML at all');
        $this->assertSame([], $broken, "Generated XML that will not parse:\n" . implode("\n", $broken));
    }

    /**
     * Language files parse, and the strings survive the round trip.
     *
     * Joomla reads these with `parse_ini_string` in RAW mode and then replaces
     * `\"` with `"`. An unescaped quote does not fail: it truncates the value
     * at that point, silently, which is how `_QQ_` came to exist and why
     * dropping it needed the escaping to be right.
     */
    #[DataProvider('goldenModels')]
    public function testEveryGeneratedLanguageFileParses(string $name): void
    {
        $broken  = [];
        $checked = 0;

        foreach ($this->generate($name) as $path => $contents) {
            if (!str_ends_with($path, '.ini')) {
                continue;
            }

            $checked++;

            $lines  = preg_split('/\R/', $contents) ?: [];
            $parsed = @parse_ini_string($contents, false, INI_SCANNER_RAW);

            if ($parsed === false) {
                $broken[] = $path . ': not a readable ini file';

                continue;
            }

            $keys = \count(array_filter(
                $lines,
                static fn (string $line): bool => $line !== '' && !str_starts_with(ltrim($line), ';')
            ));

            if ($keys !== \count($parsed)) {
                $broken[] = $path . ': ' . $keys . ' lines but ' . \count($parsed) . ' strings read back';
            }
        }

        $this->assertGreaterThan(0, $checked, $name . ' generated no language file at all');
        $this->assertSame([], $broken, "Generated language files that do not read back:\n" . implode("\n", $broken));
    }

    /**
     * The install SQL has balanced statements.
     *
     * There is no SQL parser to hand and adding one for this would be a
     * dependency for a check a person can state in a sentence: every statement
     * ends in a semicolon, and every bracket that opens closes.
     */
    #[DataProvider('goldenModels')]
    public function testGeneratedSqlIsBalanced(string $name): void
    {
        $broken  = [];
        $checked = 0;

        foreach ($this->generate($name) as $path => $contents) {
            if (!str_ends_with($path, '.sql')) {
                continue;
            }

            $checked++;

            if (substr_count($contents, '(') !== substr_count($contents, ')')) {
                $broken[] = $path . ': unbalanced brackets';
            }

            if (trim($contents) !== '' && !str_ends_with(rtrim($contents), ';')) {
                $broken[] = $path . ': the last statement is not terminated';
            }
        }

        $this->assertGreaterThan(0, $checked, $name . ' generated no SQL at all');
        $this->assertSame([], $broken, implode("\n", $broken));
    }

    /**
     * The one that would have caught the `id="List"` bug: no generated file
     * still holds an unreplaced template variable.
     *
     * `strict_variables` now throws for a name nobody supplies, but a name that
     * is supplied as an empty string still renders as nothing, and a literal
     * `{{` left behind by a broken tag renders as itself.
     */
    #[DataProvider('goldenModels')]
    public function testNoGeneratedFileStillHoldsATemplateTag(string $name): void
    {
        $leftovers = [];

        foreach ($this->generate($name) as $path => $contents) {
            foreach (['{{', '{%'] as $tag) {
                if (str_contains($contents, $tag)) {
                    $leftovers[] = $path . ': ' . $tag;
                }
            }
        }

        $this->assertSame([], $leftovers, "Template tags in generated output:\n" . implode("\n", $leftovers));
    }

    /**
     * @return array<string, string>
     */
    private function generate(string $name): array
    {
        static $runs = [];

        if (!isset($runs[$name])) {
            $runs[$name] = GenerationHarness::runJson(
                (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json')
            )->all();
        }

        return $runs[$name];
    }
}
