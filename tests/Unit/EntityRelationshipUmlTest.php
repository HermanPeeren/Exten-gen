<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Diagram\EntityRelationshipUml;
use Yepr\Component\Extengen\Tests\Support\FormSpelling;

/**
 * The ERD draws a project the way the screens store it.
 *
 * 1.4's `FieldKind` fixed the generators and missed the ERD view, which read a
 * field's kind itself in the spelling 3.5 retired. So every project saved
 * through the screens drew as boxes with blank types and no relations, while
 * the one project seeded from a pre-3.5 fixture drew perfectly - which is the
 * one somebody looks at to check the ERD works.
 *
 * The same comparison `FieldKindTest` makes for generated files, made for the
 * diagram: the old spelling and the new must draw the same thing. And the
 * claim the plan already made - that no file outside `FieldKind` reads
 * `field_type` - is checked rather than stated, because it was stated and it
 * was not true.
 *
 * @since  1.6.4
 */
final class EntityRelationshipUmlTest extends TestCase
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function models(): array
    {
        return array_map(
            static fn (string $p): array => [basename($p, '.json')],
            glob(\dirname(__DIR__) . '/Fixtures/golden/models/*.json') ?: []
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function model(string $name): array
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden/models/' . $name . '.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param  array<string, mixed>  $model
     */
    private static function uml(array $model): string
    {
        return EntityRelationshipUml::of(
            json_decode((string) json_encode($model, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Both spellings draw the same diagram.
     */
    #[DataProvider('models')]
    public function testBothSpellingsDrawTheSameDiagram(string $name): void
    {
        $model = self::model($name);

        $this->assertSame(self::uml($model), self::uml(FormSpelling::asGeneratedFormsWriteIt($model)));
    }

    /**
     * And what they draw has relations in it, or the comparison above is empty.
     *
     * Asserted on the new spelling, because that is the one that drew none.
     */
    public function testTheSavedSpellingDrawsItsRelations(): void
    {
        $uml = self::uml(FormSpelling::asGeneratedFormsWriteIt(self::model('balloonplanning')));

        $this->assertSame(5, substr_count($uml, '..o{ '), 'BalloonPlanning has five references.');
        $this->assertStringContainsString(' *balloon : Balloon  <<FK>>', $uml);
        $this->assertStringContainsString(' *capacity : Integer', $uml);
    }

    /**
     * A derived language's project draws its relations too.
     *
     * BalloonLedger is written in LedgerLang, which derives from ER1 and keeps
     * its `Field`, `Property` and `EntityReferenceField`. Its model has only
     * ever been stored in the spelling the generated forms write, so it is the
     * project that showed the defect on the development site: six entities
     * and not one line between them.
     */
    public function testBalloonLedgerDrawsItsRelations(): void
    {
        $uml = EntityRelationshipUml::of(json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/golden-joomla6-ledgerlang/models/balloonledger.json'),
            false,
            512,
            \JSON_THROW_ON_ERROR
        ));

        $this->assertSame(5, substr_count($uml, '..o{ '), 'BalloonLedger has five references.');
        $this->assertStringContainsString('Journal ||..o{ JournalEntry', $uml);
        $this->assertStringContainsString('Account ||..o{ EntryLine', $uml);
        $this->assertStringContainsString('Party |o..o{ EntryLine', $uml);
        $this->assertStringContainsString(' *account : Account  <<FK>>', $uml);
        $this->assertStringContainsString(' *code : Short_Text', $uml);
    }

    /**
     * Nothing outside `FieldKind` reads a field's kind for itself.
     *
     * Every PHP file and template in the component, read as text. A file that
     * names `field_type` is a file that will be wrong the next time the
     * language renames it, and the ERD view was that file for a year.
     */
    public function testOnlyFieldKindReadsTheDiscriminator(): void
    {
        $root  = \dirname(__DIR__, 2) . '/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $found = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'FieldKind.php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), 'field_type')) {
                $found[] = substr($file->getPathname(), \strlen($root) + 1);
            }
        }

        $this->assertSame([], $found, 'Ask FieldKind instead.');
    }
}
