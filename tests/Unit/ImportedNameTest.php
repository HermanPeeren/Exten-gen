<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;

/**
 * What the LionWeb import calls a project, and whether its own form takes it.
 *
 * The import named JCB's demo blueprint `MDE demo`, after its root node's id,
 * and the project form - letters only, `LetterRule` - refused to save it. A
 * project that cannot be saved in the form that opened it is a project whose
 * first edit is a rename nobody asked for.
 *
 * Checked against the rule as `LetterRule.php` declares it, read the way
 * `LetterRuleTest` reads it, so that a name made here and a name refused there
 * cannot pass separately.
 *
 * @since  1.6.5
 */
final class ImportedNameTest extends TestCase
{
    private function rule(): string
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2)
            . '/src/administrator/components/com_extengen/src/Rule/LetterRule.php'
        );

        preg_match('/\$regex\s*=\s*\'([^\']*)\'/', $source, $pattern);
        preg_match('/\$modifiers\s*=\s*\'([^\']*)\'/', $source, $modifiers);

        return '/' . $pattern[1] . '/' . $modifiers[1];
    }

    /**
     * @return array<string, array{list<string>, string}>
     */
    public static function names(): array
    {
        return [
            'the demo blueprint'     => [['MDE-demo', 'JCB model'], 'MDEDemo'],
            'hello world'            => [['hello-world', 'JCB model'], 'HelloWorld'],
            'digits go'              => [['Demo J6', 'JCB model'], 'DemoJ'],
            'already a name'         => [['DemoJ', 'JCB model'], 'DemoJ'],
            'no letters in the id'   => [['2026-10', 'JCB model'], 'JCBModel'],
            'a GUID is not a name'   => [['3745af8f-f96b-4e17-831e-eb4062cd4389', 'JCB model'], 'JCBModel'],
            'a language with digits' => [['', 'ER1 model'], 'ERModel'],
            'nothing at all'         => [['', ''], 'Imported'],
        ];
    }

    /**
     * @param  list<string>  $candidates
     */
    #[DataProvider('names')]
    public function testTheImportNamesAProjectItsFormWillSave(array $candidates, string $expected): void
    {
        $name = Project::letterName(...$candidates);

        $this->assertSame($expected, $name);
        $this->assertSame(1, preg_match($this->rule(), $name), $name . ' is refused by LetterRule.');
    }
}
