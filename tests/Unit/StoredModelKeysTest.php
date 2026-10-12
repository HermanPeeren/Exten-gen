<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Core\Lionweb\Chunk;
use Yepr\Gen\Core\Lionweb\InstanceChunk;
use Yepr\Gen\Core\Lionweb\InstanceModel;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageManifest;

/**
 * What a save stores as the model, and what an export takes back off it.
 *
 * A project imported from LionWeb, opened in the browser and saved, could not
 * be exported again: "root.tags is not a feature of Blueprint in this
 * language". `FormController::save()` adds `tags => []` to every post whose
 * form has no tags field, and `ProjectModel::save()` serialised everything it
 * was handed except `id`. The round trip had been proved through
 * `getForm()`, `filter()` and `save()` in code, which never passes the
 * controller - so the one key only a browser save adds was the one nobody
 * had seen.
 *
 * The steps below are the ones a browser save and an export take, in the
 * order they take them, with Joomla's own contribution written out where
 * Joomla would make it. The language is small enough to read; the shape of
 * the problem does not depend on its size.
 *
 * @since  1.6.5
 */
final class StoredModelKeysTest extends TestCase
{
    private const LANGUAGE = 'shelf';
    private const VERSION  = '1.0';

    /**
     * The chrome as this component ships it.
     */
    private function chromeKeys(): array
    {
        return Metalanguages::chromeKeys((string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/' . Metalanguages::CHROME_FORM
        ));
    }

    private function language(): PackageManifest
    {
        return PackageManifest::fromJson((string) json_encode([
            'format'   => MetalanguagePackage::FORMAT,
            'name'     => 'Shelf',
            'version'  => self::VERSION,
            'root'     => 'shelf',
            'concepts' => [
                [
                    'key' => 'shelf', 'name' => 'Shelf', 'features' => [
                        ['key' => 'shelf-k-label', 'name' => 'label'],
                        ['key' => 'shelf-k-book', 'name' => 'book', 'multiple' => true],
                    ],
                ],
                [
                    'key' => 'book', 'name' => 'Book', 'features' => [
                        ['key' => 'book-k-title', 'name' => 'title'],
                    ],
                ],
            ],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function chunk(): array
    {
        $meta = static fn (string $key): array => [
            'language' => self::LANGUAGE, 'version' => self::VERSION, 'key' => $key,
        ];

        $node = static fn (string $id, string $concept, array $properties, array $children, ?string $parent): array => [
            'id'           => $id,
            'classifier'   => $meta($concept),
            'properties'   => array_map(
                static fn (string $key, string $value): array => ['property' => $meta($key), 'value' => $value],
                array_keys($properties),
                $properties
            ),
            'containments' => $children === [] ? [] : [['containment' => $meta('shelf-k-book'), 'children' => $children]],
            'references'   => [],
            'annotations'  => [],
            'parent'       => $parent,
        ];

        return [
            'serializationFormatVersion' => '2024.1',
            'languages'                  => [['key' => self::LANGUAGE, 'version' => self::VERSION]],
            'nodes'                      => [
                $node('my-shelf', 'shelf', ['shelf-k-label' => 'Science fiction'], ['b-1', 'b-2'], null),
                $node('b-1', 'book', ['book-k-title' => 'Dune'], [], 'my-shelf'),
                $node('b-2', 'book', ['book-k-title' => 'Solaris'], [], 'my-shelf'),
            ],
        ];
    }

    /**
     * What `LionwebModel::convert()` stores: the reader's model, and the name.
     *
     * @return array<string, mixed>
     */
    private function imported(): array
    {
        $reader = InstanceModel::read(Chunk::fromArray($this->chunk()), $this->language());

        $this->assertSame([], $reader->diagnostics());

        return ['name' => 'MyShelf'] + $reader->toStoredModel();
    }

    /**
     * What a browser save hands `ProjectModel::save()`, and what it stores.
     *
     * The form posts the chrome it renders - `name` and the binding - and the
     * model half. `FormController::save()` then adds `tags`, which no field
     * on the screen declares.
     *
     * @param  array<string, mixed>  $model
     *
     * @return array<string, mixed>
     */
    private function browserSave(array $model): array
    {
        $posted = $model + ['metalanguage' => 'Shelf|1.0', 'id' => 7];

        // FormController::save(), Joomla 6.1.
        if (!isset($posted['tags'])) {
            $posted['tags'] = [];
        }

        // ProjectModel::save().
        $posted['modelVersion'] = Project::CURRENT_VERSION;

        unset($posted['metalanguage']);

        $stored = Project::modelOf($posted, $this->chromeKeys());

        unset($stored['id']);

        return $stored;
    }

    /**
     * What `LionwebModel::export()` writes.
     *
     * @param  array<string, mixed>  $stored
     *
     * @return array{chunk: array<string, mixed>, diagnostics: array<int, array<string, string>>}
     */
    private function export(array $stored, bool $clean = true): array
    {
        if ($clean) {
            $stored = Project::modelOf($stored, $this->chromeKeys());
        }

        unset($stored['name'], $stored['id'], $stored['modelVersion']);

        $writer = InstanceChunk::of($this->language());

        return ['chunk' => $writer->write($stored), 'diagnostics' => $writer->diagnostics()];
    }

    /**
     * @param  array<string, mixed>  $chunk
     *
     * @return array<string, array<string, mixed>>
     */
    private function comparable(array $chunk): array
    {
        $nodes = [];

        foreach ($chunk['nodes'] as $node) {
            ksort($node);
            $nodes[$node['id']] = $node;
        }

        ksort($nodes);

        return $nodes;
    }

    public function testTheChromeNamesTheKeysItPosts(): void
    {
        $keys = $this->chromeKeys();

        foreach (['id', 'name', 'metalanguage', 'alias', 'published', 'catid', 'access', 'params'] as $key) {
            $this->assertContains($key, $keys);
        }

        // Inside `params`, so it posts as part of that key and not as one of
        // its own.
        $this->assertNotContains('show_name', $keys);
    }

    /**
     * The bug itself: Joomla's `tags` in the model, and an export refused.
     *
     * Exported without the cleaning, which is what 1.6.4 did with a model a
     * browser save had written - the reason this test can say the cleaning is
     * what fixes it rather than merely that the round trip now works.
     */
    public function testATagsKeyInAStoredModelIsWhatTheExportRefused(): void
    {
        $stored = $this->imported() + ['tags' => [], 'modelVersion' => '1.0'];

        $diagnostics = $this->export($stored, false)['diagnostics'];

        $this->assertNotSame([], $diagnostics);
        $this->assertStringContainsString('tags', $diagnostics[0]['message']);
    }

    public function testABrowserSaveStoresTheModelAndNotTheRow(): void
    {
        $imported = $this->imported();
        $stored   = $this->browserSave($imported);

        $this->assertArrayNotHasKey('tags', $stored);
        $this->assertArrayNotHasKey('id', $stored);
        $this->assertArrayNotHasKey('metalanguage', $stored);

        // Everything that was there is still there, and only the version is new.
        $this->assertSame(
            array_merge(array_keys($imported), ['modelVersion']),
            array_keys($stored)
        );
    }

    /**
     * Imported, edited in the browser, saved, exported: the same chunk with the
     * one value that was edited.
     */
    public function testAnImportedModelRoundTripsThroughABrowserSave(): void
    {
        $model = $this->imported();

        $model['label'] = 'Speculative fiction';

        $exported = $this->export($this->browserSave($model));

        $this->assertSame([], $exported['diagnostics']);

        $expected = $this->chunk();

        $expected['nodes'][0]['properties'][0]['value'] = 'Speculative fiction';

        $this->assertSame($this->comparable($expected), $this->comparable($exported['chunk']));
    }

    /**
     * A project saved before this still carries `tags`, and exports anyway.
     */
    public function testAModelStoredWithTagsExportsWithoutBeingSavedAgain(): void
    {
        $stored = $this->imported() + ['tags' => [], 'modelVersion' => '1.0'];

        $exported = $this->export($stored);

        $this->assertSame([], $exported['diagnostics']);
        $this->assertSame($this->comparable($this->chunk()), $this->comparable($exported['chunk']));
    }
}
