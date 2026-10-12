<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Helper\BrowserSave;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;

/**
 * A browser save that changes nothing stores what was there.
 *
 * Two things come back from a form post different from the stored model
 * without anybody typing them (the plan's step 6.4): every line break in a
 * textarea arrives as CRLF, and a property the model did not have arrives as
 * `''` or as the first option of a list. `BrowserSave` puts both back against
 * the stored model; these are its rules one by one, then Hello World posted
 * the way a browser posts it.
 *
 * @since  1.6.6
 */
final class BrowserSaveTest extends TestCase
{
    private const HELLO_WORLD = 'jcbinout/data/hello-world.instance.lionweb.json';

    /**
     * A stored node as the LionWeb import writes one.
     *
     * @param  array<string, mixed>  $properties
     *
     * @return array<string, mixed>
     */
    private function node(string $id, array $properties, string $concept = 'Snippet'): array
    {
        return $properties + ['LIonWeb_key' => $concept, 'LIonWeb_id' => $id];
    }

    /**
     * One property of one node through the line-ending rules.
     */
    private function saved(string $posted, ?string $stored): string
    {
        $was = $stored === null ? [] : ['snippet' => $stored];

        $model = BrowserSave::lineEndings(
            ['root' => $this->node('n-1', ['snippet' => $posted])],
            ['root' => $this->node('n-1', $was)]
        );

        return $model['root']['snippet'];
    }

    public function testAValueWithoutCrlfIsKeptAsPosted(): void
    {
        // Rule 1: a text box, a one-line value, or LF from a save made in code.
        $this->assertSame('one line', $this->saved('one line', "was\nLF"));
        $this->assertSame("in code\nLF", $this->saved("in code\nLF", "was\r\nCRLF"));
    }

    public function testAValueStoredWithCrlfComesBackWithCrlf(): void
    {
        // Rule 2.
        $this->assertSame("a\r\nb\r\nc", $this->saved("a\r\nb\r\nc", "a\r\nb"));
    }

    public function testAValueStoredWithLfOnlyComesBackWithLf(): void
    {
        // Rule 3, the case that matters: 5 of Hello World's 30, 9 of the demo
        // blueprint's 868.
        $this->assertSame("<?php\n\necho 1;\n", $this->saved("<?php\r\n\r\necho 1;\r\n", "<?php\n\necho 1;\n"));
    }

    public function testANewLineBreakIsLf(): void
    {
        // Rule 3: stored without a line break, or not stored at all.
        $this->assertSame("first\nsecond", $this->saved("first\r\nsecond", 'first'));
        $this->assertSame("first\nsecond", $this->saved("first\r\nsecond", null));
    }

    public function testALeadingLineBreakTheTextareaDroppedIsKept(): void
    {
        // Rule 4: HTML drops the newline straight after <textarea>.
        $this->assertSame("\nbody\nmore", $this->saved("body\r\nmore", "\nbody\nmore"));
        $this->assertSame("\r\nbody\r\nmore", $this->saved("body\r\nmore", "\r\nbody\r\nmore"));
        $this->assertSame("\n", $this->saved('', "\n"));

        // Only one: the textarea drops one, so a second was edited away.
        $this->assertSame('body', $this->saved('body', "\n\nbody"));
    }

    public function testANewProjectGetsLf(): void
    {
        $model = BrowserSave::lineEndings(['code' => "a\r\nb", 'LIonWeb_id' => ''], []);

        $this->assertSame("a\nb", $model['code']);
    }

    public function testANodeIsFoundByItsIdWhereverItWasMoved(): void
    {
        $stored = ['rows' => [
            'rows0' => $this->node('crlf', ['code' => "x\r\ny"]),
            'rows1' => $this->node('lf', ['code' => "x\ny"]),
        ]];

        // Moved on screen: the subform renumbers, the ids go with the rows.
        $posted = ['rows' => [
            'rows0' => $this->node('lf', ['code' => "x\r\ny"]),
            'rows1' => $this->node('crlf', ['code' => "x\r\ny"]),
            'rows2' => $this->node('', ['code' => "new\r\nrow"]),
        ]];

        $model = BrowserSave::lineEndings($posted, $stored);

        $this->assertSame("x\ny", $model['rows']['rows0']['code']);
        $this->assertSame("x\r\ny", $model['rows']['rows1']['code']);
        $this->assertSame("new\nrow", $model['rows']['rows2']['code'], 'a new row has an empty id and matches nothing');
    }

    public function testANodeWithoutAnIdIsFoundByItsPath(): void
    {
        // An ER1 project: nothing carries an id.
        $stored = [
            'description' => "kept\r\nCRLF",
            'entities'    => ['entities0' => ['code' => "kept\r\nCRLF"], 'entities1' => ['code' => "was\nLF"]],
        ];

        $posted = [
            'description' => "kept\r\nCRLF",
            'entities'    => ['entities0' => ['code' => "kept\r\nCRLF"], 'entities1' => ['code' => "was\r\nLF"]],
        ];

        $this->assertSame([
            'description' => "kept\r\nCRLF",
            'entities'    => ['entities0' => ['code' => "kept\r\nCRLF"], 'entities1' => ['code' => "was\nLF"]],
        ], BrowserSave::lineEndings($posted, $stored));
    }

    public function testAPathDoesNotMatchAStoredNodeThatHasAnId(): void
    {
        // A new row lands where a stored row was: same path, but the stored
        // one is found by its id and this one is new.
        $stored = ['rows' => ['rows0' => $this->node('n-1', ['code' => "a\r\nb"])]];
        $posted = ['rows' => ['rows0' => $this->node('', ['code' => "a\r\nb"])]];

        $this->assertSame("a\nb", BrowserSave::lineEndings($posted, $stored)['rows']['rows0']['code']);
    }

    public function testTheReservedKeysAreLeftAlone(): void
    {
        $posted = $this->node("odd\r\nid", ['LIonWeb_language' => "x\r\ny"]);

        $this->assertSame($posted, BrowserSave::lineEndings($posted, []));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function shown(): array
    {
        return ['AdminView' => ['mysql_table_engine' => 'AdminViewMysqlTableEngine-MYISAM']];
    }

    public function testAnAbsentPropertyLeftEmptyStaysAbsent(): void
    {
        $stored = $this->node('v-1', ['name' => 'Hello'], 'AdminView');
        $posted = $this->node('v-1', ['name' => 'Hello', 'note' => '', 'mysql_table_engine' => 'AdminViewMysqlTableEngine-MYISAM'], 'AdminView');

        $this->assertSame($stored, BrowserSave::absentKept($posted, $stored, $this->shown()));
    }

    public function testAnAbsentPropertyFilledInIsStored(): void
    {
        $stored = $this->node('v-1', [], 'AdminView');
        $posted = $this->node('v-1', ['note' => 'typed', 'mysql_table_engine' => 'AdminViewMysqlTableEngine-INNODB'], 'AdminView');

        $this->assertSame($posted, BrowserSave::absentKept($posted, $stored, $this->shown()));
    }

    public function testAPresentPropertyKeepsWhatWasPosted(): void
    {
        // Present and '' stays '', and present and emptied becomes ''. 91 of
        // Hello World's 595 values are '', and they are not absent.
        $stored = $this->node('v-1', ['note' => '', 'title' => 'was', 'mysql_table_engine' => 'AdminViewMysqlTableEngine-MYISAM'], 'AdminView');
        $posted = $this->node('v-1', ['note' => '', 'title' => '', 'mysql_table_engine' => 'AdminViewMysqlTableEngine-MYISAM'], 'AdminView');

        $this->assertSame($posted, BrowserSave::absentKept($posted, $stored, $this->shown()));
    }

    public function testOnlyANodeMatchedByIdKeepsItsAbsentProperties(): void
    {
        // An ER1 project has no absent to keep: a project made on screen is
        // saved once with no model at all, so on its first real save every
        // property was absent.
        $er1 = BrowserSave::absentKept(['entities' => ['entities0' => ['note' => '']]], ['name' => 'X'], []);
        $this->assertSame(['entities' => ['entities0' => ['note' => '']]], $er1);

        // And a new node has nothing stored to be absent from.
        $new = BrowserSave::absentKept(['rows' => ['rows0' => $this->node('', ['note' => ''])]], $this->node('root', []), []);
        $this->assertSame('', $new['rows']['rows0']['note']);
    }

    public function testTheFormsSayWhatTheyShowForAnAbsentProperty(): void
    {
        $directory = sys_get_temp_dir() . '/extengen-shown-' . bin2hex(random_bytes(4));
        mkdir($directory);

        file_put_contents($directory . '/adminView.xml', <<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <form>
              <fieldset name="adminView">
                <field name="name" filter="raw" type="text"/>
                <field name="engine" filter="raw" type="list">
                  <option value="Engine-MYISAM">MYISAM</option>
                  <option value="Engine-INNODB">INNODB</option>
                </field>
                <field name="charset" filter="raw" type="list" default="Charset-UTF8">
                  <option value="Charset-BIG5">BIG5</option>
                  <option value="Charset-UTF8">UTF8</option>
                </field>
                <field name="rows" filter="raw" type="number" default="5"/>
                <field name="tags" type="list" multiple="true"><option value="a">a</option></field>
                <field name="LIonWeb_key" value="AdminView" type="hidden"/>
                <field name="LIonWeb_id" type="hidden"/>
              </fieldset>
            </form>
            XML);

        // ER1's forms carry no concept marker.
        file_put_contents($directory . '/entity.xml', '<form><field name="kind" type="list"><option value="a">a</option></field></form>');

        try {
            $this->assertSame(
                ['AdminView' => ['engine' => 'Engine-MYISAM', 'charset' => 'Charset-UTF8', 'rows' => '5']],
                Metalanguages::shownWhenAbsent($directory)
            );

            // The form's marker is the concept's name, a stored node's its
            // LionWeb key: JCB's `Field` is `field` in a chunk.
            $this->assertSame(
                ['Engine-MYISAM', 'Engine-MYISAM'],
                array_column(Metalanguages::shownWhenAbsent($directory, [['key' => 'admin_view', 'name' => 'AdminView']]), 'engine')
            );
        } finally {
            array_map('unlink', glob($directory . '/*.xml') ?: []);
            rmdir($directory);
        }
    }

    /**
     * Hello World from JcbInOut's sample data, if a sibling clone has it.
     *
     * Looked for upward from here, so that a worktree under `.claude/` finds
     * the clone beside the repository as well.
     *
     * @return array<string, mixed>
     */
    private function helloWorld(): array
    {
        $directory = \dirname(__DIR__, 2);

        for ($up = 0; $up < 5; $up++) {
            $directory = \dirname($directory);

            if (is_file($directory . '/' . self::HELLO_WORLD)) {
                return json_decode((string) file_get_contents($directory . '/' . self::HELLO_WORLD), true, 512, JSON_THROW_ON_ERROR);
            }
        }

        $this->markTestSkipped('No ' . self::HELLO_WORLD . ' beside this repository.');
    }

    /**
     * A chunk as a stored model, in the import's shape.
     *
     * Properties by key rather than by name, which needs no language and
     * changes nothing the rules look at: they match on whatever the key is.
     *
     * @param  array<string, array<string, mixed>>  $nodes
     *
     * @return array<string, mixed>
     */
    private function stored(array $nodes, string $id): array
    {
        $node = $nodes[$id];
        $row  = [];

        foreach ($node['properties'] as $property) {
            $row[$property['property']['key']] = $property['value'];
        }

        foreach ($node['references'] as $reference) {
            $row[$reference['reference']['key']] = $reference['targets'][0]['reference'] ?? '';
        }

        foreach ($node['containments'] as $containment) {
            $key   = $containment['containment']['key'];
            $group = [];

            foreach ($containment['children'] as $position => $child) {
                $group[$key . $position] = $this->stored($nodes, $child);
            }

            $row[$key] = $group;
        }

        return $row + ['LIonWeb_key' => $node['classifier']['key'], 'LIonWeb_id' => $id];
    }

    /**
     * What a browser posts for a stored model, every property on a textarea.
     *
     * Every line break as CRLF, the newline after `<textarea>` dropped, and
     * every property the concept has elsewhere in the model posted as `''`
     * where this node does not have it - what an empty box sends.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, list<string>>  $features  Concept => every property key seen on it.
     *
     * @return array<string, mixed>
     */
    private function posted(array $node, array $features): array
    {
        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $node[$key] = $this->posted($value, $features);
            } elseif (\is_string($value) && !str_starts_with($key, 'LIonWeb_')) {
                $crlf       = (string) preg_replace("/\r\n|\r|\n/", "\r\n", $value);
                $node[$key] = str_starts_with($crlf, "\r\n") ? substr($crlf, 2) : $crlf;
            }
        }

        foreach ($features[$node['LIonWeb_key'] ?? ''] ?? [] as $key) {
            $node[$key] ??= '';
        }

        return $node;
    }

    /**
     * @param  array<array-key, mixed>  $model
     *
     * @return array<array-key, mixed>
     */
    private function sorted(array $model): array
    {
        ksort($model);

        return array_map(fn ($value) => \is_array($value) ? $this->sorted($value) : $value, $model);
    }

    public function testHelloWorldPostedFromTextareasComesBackAsItWas(): void
    {
        $chunk = $this->helloWorld();
        $nodes = array_column($chunk['nodes'], null, 'id');
        $roots = array_values(array_filter($chunk['nodes'], static fn (array $node): bool => ($node['parent'] ?? null) === null));

        $this->assertCount(1, $roots);

        $features = [];

        foreach ($chunk['nodes'] as $node) {
            foreach ($node['properties'] as $property) {
                $features[$node['classifier']['key']][$property['property']['key']] = $property['property']['key'];
            }
        }

        $features = array_map('array_values', $features);
        $stored   = $this->stored($nodes, $roots[0]['id']);
        $posted   = $this->posted($stored, $features);

        // What it would have stored before: 30 values with line breaks, all
        // CRLF now, and properties nobody gave a value posted as ''.
        $multiline = 0;
        $lfOnly    = [];

        array_walk_recursive($stored, static function ($value, $key) use (&$multiline, &$lfOnly): void {
            if (\is_string($value) && str_contains($value, "\n")) {
                $multiline++;

                if (!str_contains($value, "\r\n")) {
                    $lfOnly[] = $value;
                }
            }
        });

        $this->assertSame(30, $multiline);
        $this->assertCount(5, $lfOnly);
        $this->assertNotSame($this->sorted($stored), $this->sorted($posted), 'the post differs from what was stored');

        $saved = BrowserSave::absentKept(BrowserSave::lineEndings($posted, $stored), $stored, []);

        $this->assertSame($this->sorted($stored), $this->sorted($saved), 'nothing changed, so nothing differs');

        $kept = [];

        array_walk_recursive($saved, static function ($value) use (&$kept, $lfOnly): void {
            if (\in_array($value, $lfOnly, true)) {
                $kept[] = $value;
            }
        });

        $this->assertCount(5, $kept, 'the five LF-only values are LF again');
    }
}
