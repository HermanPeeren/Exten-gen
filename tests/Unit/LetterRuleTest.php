<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a project may be called, on the side that decides.
 *
 * A project's name becomes the component name, the namespace segment, the
 * table prefix and every class name in the generated extension, so the rule is
 * strict: letters, and at least one of them.
 *
 * It is applied twice - `LetterRule` here, `admin-extengen-letter.js` in the
 * browser - and the browser's copy is the one a user meets first. That makes
 * this the copy nobody sees fail, and therefore the one worth testing: a form
 * posted by anything other than the edit screen is validated only here.
 *
 * The two were not quite the same rule. PCRE's `$` matches immediately before
 * a final newline unless told otherwise, and JavaScript's does not, so this
 * side accepted `Conference\n` while the browser refused it - a component name
 * with a line break in it, on its way into a namespace. The `D` modifier is
 * what closed that, and the case is below.
 *
 * Read through the pattern rather than by building a `Form`, because a rule
 * that needs a Joomla form around it to be asked a question is a rule this
 * suite cannot ask. `letter.test.mjs` pins the pattern and the modifiers
 * against the JavaScript copy from the other direction.
 *
 * @since  1.4.0
 */
final class LetterRuleTest extends TestCase
{
    /**
     * The rule as the class declares it.
     */
    private function expression(): string
    {
        $source = (string) file_get_contents(
            \dirname(__DIR__, 2)
            . '/src/administrator/components/com_extengen/src/Rule/LetterRule.php'
        );

        $this->assertSame(
            1,
            preg_match('/\$regex\s*=\s*\'([^\']*)\'/', $source, $pattern),
            'LetterRule declares no regex.'
        );

        $this->assertSame(
            1,
            preg_match('/\$modifiers\s*=\s*\'([^\']*)\'/', $source, $modifiers),
            'LetterRule declares no modifiers.'
        );

        // Assembled the way Joomla's FormRule assembles it.
        return '/' . $pattern[1] . '/' . $modifiers[1];
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function names(): array
    {
        return [
            'letters'              => ['Conference', true],
            'lower case'           => ['conference', true],
            'upper case'           => ['CONFERENCE', true],
            'one letter'           => ['a', true],
            'nothing'              => ['', false],
            'a trailing digit'     => ['Conference2', false],
            'a leading digit'      => ['2Conference', false],
            'a space'              => ['My Conference', false],
            'a hyphen'             => ['My-Conference', false],
            'an underscore'        => ['My_Conference', false],
            // The two this rule used to let through.
            'a trailing newline'   => ["Conference\n", false],
            'a leading newline'    => ["\nConference", false],
        ];
    }

    #[DataProvider('names')]
    public function testWhatMayBeAProjectName(string $name, bool $allowed): void
    {
        $this->assertSame(
            $allowed,
            preg_match($this->expression(), $name) === 1,
            $allowed
                ? json_encode($name) . ' should be allowed as a project name.'
                : json_encode($name) . ' should not be allowed as a project name.'
        );
    }
}
