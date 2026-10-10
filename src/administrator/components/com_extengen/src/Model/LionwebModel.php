<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepositoryAwareInterface;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepositoryAwareTrait;
use Yepr\Gen\Core\Lionweb\Chunk;
use Yepr\Gen\Core\Lionweb\InstanceChunk;
use Yepr\Gen\Core\Lionweb\InstanceModel;
use Yepr\Gen\Core\Package\PackageManifest;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Reading a model written in LionWeb in as a project.
 *
 * The counterpart of Meta-gen's model of the same name, one level down. That
 * one reads a chunk holding a *language* and stores a metalanguage; this reads
 * a chunk holding a *model written in one* and stores a project. A blueprint
 * JcbInOut exported from a JCB installation arrives here as something somebody
 * can open and edit.
 *
 * **The chunk says which language it is in, so nobody is asked.** Its envelope
 * names the language and the version, which is exactly what identifies an
 * imported metalanguage on this site. A dropdown offering a choice would be
 * offering the chance to get it wrong: a model read against the wrong language
 * does not fail, it silently produces fields that are not there.
 *
 * **The manifest comes off the row, not out of the entry.** `MetalanguageEntry`
 * keeps a concept's key and name, which is what naming a concept in a rule
 * needs. Turning a chunk back into a model needs the rest - which features are
 * lists - so this reads the manifest column the importer stored and hands the
 * whole of it over.
 *
 * @since  1.4.0
 */
class LionwebModel extends BaseDatabaseModel implements ProjectRepositoryAwareInterface
{
    use ProjectRepositoryAwareTrait;

    /**
     * A file JcbInOut leaves on the same site, offered as the obvious one to
     * read. The two components meet on disk rather than over a wire.
     *
     * @since  1.4.0
     */
    public const JCBINOUT =
        'administrator/components/com_jcbinout/data/derived/blueprint.instance.lionweb.json';

    /**
     * Where a chunk may be read from.
     *
     * Under the site and nothing else. An administrator can already reach the
     * filesystem by other means, so this is not a wall - but a field that reads
     * any path the web server can is a worse habit than one that does not, and
     * costs nothing to avoid.
     *
     * @throws \RuntimeException  When the path is outside the site, or unreadable.
     *
     * @since  1.4.0
     */
    public function readChunk(string $path): string
    {
        $root  = realpath(JPATH_ROOT);
        $given = realpath($path !== '' && $path[0] === '/' ? $path : JPATH_ROOT . '/' . $path);

        if ($given === false || !is_file($given)) {
            throw new \RuntimeException('There is no file at ' . $path . '.');
        }

        if ($root === false || !str_starts_with($given, $root)) {
            throw new \RuntimeException('A LionWeb chunk has to be a file under this site.');
        }

        $contents = file_get_contents($given);

        if ($contents === false) {
            throw new \RuntimeException('The file at ' . $path . ' could not be read.');
        }

        return $contents;
    }

    /**
     * Convert a chunk into the row a project is stored as.
     *
     * No writing here, which is what lets the conversion be exercised without
     * creating anything. Storing it is the next call.
     *
     * @return array{name: string, key: string, version: string, form_data: string, groups: int, diagnostics: array<int, array<string, string>>}
     *
     * @throws \RuntimeException  When the chunk holds no model this site can read.
     *
     * @since  1.4.0
     */
    public function convert(string $json): array
    {
        $chunk     = Chunk::fromJson($json);
        $languages = $chunk->languages();

        if ($languages === []) {
            throw new \RuntimeException(
                'This chunk does not say which language it is written in, so there is'
                . ' nothing to read it against.'
            );
        }

        // The language the nodes are classified by. A chunk may declare more
        // than one - LionCore-builtins travels with every language - so the
        // one the root is classified by is the one that matters.
        $root = $chunk->roots()[0] ?? null;

        // Asked before the language is looked up, because the answer does not
        // depend on it: a chunk with nothing in it is empty whichever language
        // it claims, and saying "that metalanguage is not imported" first
        // sends somebody off to install a language that would not have helped.
        if ($root === null) {
            throw new \RuntimeException(
                'This chunk holds no model: nothing in it is a node that nothing else contains.'
            );
        }

        $key     = $this->languageOf($chunk, $root, $languages[0]['key']);
        $version = $chunk->versionOf($key) ?? '';
        $stored  = $this->manifestFor($key, $version);

        $reader    = InstanceModel::read($chunk, $stored);
        $converted = $reader->toStoredModel();

        if ($converted === []) {
            // Not the same case as the one above: there is a root, and the
            // reader still made nothing of it. Its own diagnostics say why.
            throw new \RuntimeException(
                'Nothing could be read from this chunk\'s root node.'
            );
        }

        $name = $this->nameFor($root, $stored->name);

        // `name` is the project's own, from the chrome the edit screen wraps
        // every language in, and the screen reads it out of the model rather
        // than off the row. A model that did not carry it opened with an empty
        // name field over a project that has one.
        $converted = ['name' => $name] + $converted;

        return [
            'name' => $name,
            // The metalanguage's own key and version, not the chunk's. A
            // LionWeb language key and the key of the package built from it
            // need not be spelled the same - JCB's language calls itself `jcb`
            // and its package `JCB` - and what a project has to name is the
            // metalanguage as this site knows it, because that is what the
            // edit screen looks up to find its forms.
            'key'         => $stored->key,
            'version'     => $stored->version,
            'form_data'   => $this->encode($converted),
            'groups'      => \count(array_filter($converted, '\is_array')),
            'diagnostics' => $reader->diagnostics(),
        ];
    }

    /**
     * Store a converted model as a project of its own.
     *
     * Always a new row, for the same reason Meta-gen's import is: replacing one
     * that shares a name would be a guess about which of two things called
     * `HelloWorld` somebody meant, and a project is cheap to delete and
     * expensive to get back.
     *
     * @param  array{name: string, key: string, version: string, form_data: string}  $converted
     *
     * @throws \RuntimeException  When the row will not save.
     *
     * @since  1.4.0
     */
    public function store(array $converted): int
    {
        $table = $this->getTable('Project');

        try {
            $stored = $table->bind([
                'name'                 => $converted['name'],
                'alias'                => strtolower(
                    (string) preg_replace('/[^A-Za-z0-9]+/', '-', $converted['name'])
                ),
                'form_data'            => $converted['form_data'],
                'metalanguage_key'     => $converted['key'],
                'metalanguage_version' => $converted['version'],
                'published'            => 1,
            ]) && $table->check() && $table->store();
        } catch (\Throwable $e) {
            throw new \RuntimeException('The project could not be saved: ' . $e->getMessage(), 0, $e);
        }

        if (!$stored) {
            // `Table::store()` keeps its reason in the error state that
            // getError() reads, and that is deprecated, so the size is the one
            // fact worth adding: a blueprint is orders of magnitude larger than
            // anything typed into these forms, and a column too narrow for it
            // is what every refusal here has been.
            throw new \RuntimeException(sprintf(
                'The project could not be saved. It is %s bytes;'
                . ' a column too narrow to hold it is the usual cause.',
                number_format(\strlen($converted['form_data']))
            ));
        }

        return (int) $table->id;
    }

    /**
     * A project, written back out as a LionWeb chunk.
     *
     * The way back. What came in from JCB can be edited here and go out again,
     * which is the difference between importing a blueprint and being able to
     * work on one.
     *
     * @return array{json: string, nodes: int, name: string, diagnostics: array<int, array<string, string>>}
     *
     * @throws \RuntimeException  When there is no such project, or no language to write it in.
     *
     * @since  1.4.0
     */
    public function export(int $id): array
    {
        // Through the repository, which is the one place a stored model is
        // loaded. There were thirteen once, and `ModelLayerBoundaryTest`
        // exists to stop there being fourteen - it caught this one.
        $repository = $this->getProjectRepository();
        $project    = $repository->find($id);
        $binding    = $repository->binding($id);

        if ($project === null || $binding === null) {
            throw new \RuntimeException('There is no project with id ' . $id . '.');
        }

        $stored = json_decode(
            (string) json_encode($project->raw()),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        if (!\is_array($stored) || $stored === []) {
            throw new \RuntimeException(
                'This project holds no model yet, so there is nothing to write out.'
            );
        }

        // The chrome's, not the language's. Every project carries a `name` at
        // the top because the edit screen wraps every language in a form that
        // asks for one, and `convert()` puts it there on the way in - so it
        // comes off again here rather than being reported by the writer as a
        // feature the root concept does not have. A root concept that has its
        // own `name` has a collision with the chrome already, on the way in.
        //
        // `id` and `modelVersion` the same way, and for the same reason: the
        // row's own number and the shape its JSON is written in, both put
        // there by `ProjectModel::save()`. Left in, they came back out of a
        // saved project as two warnings about features the root concept does
        // not have - true, and about nothing the model said.
        unset($stored['name'], $stored['id'], $stored['modelVersion']);

        $manifest = $this->manifestFor($binding['key'], $binding['version']);

        // The model says which language it is written in, because the import
        // put it there and a hidden field on the root form carries it through
        // every save. A project *built* here rather than imported says
        // nothing, and then the language it is bound to answers for it - which
        // a manifest can only do since format 5.
        //
        // The model first, not the manifest: a chunk that arrived saying `jcb`
        // goes back out saying `jcb`, whatever the package it was read
        // against happens to call itself.
        $told = isset($stored[InstanceModel::LANGUAGE]) && $stored[InstanceModel::LANGUAGE] !== ''
            ? ''
            : $manifest->lionwebKey;

        $writer = InstanceChunk::of($manifest, $told, $told === '' ? '' : $manifest->version);
        $chunk  = $writer->write($stored);

        $json = json_encode($chunk, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException(
                'The chunk could not be encoded: ' . json_last_error_msg()
            );
        }

        return [
            'json'        => $json,
            'nodes'       => \count($chunk['nodes'] ?? []),
            'name'        => $project->name(),
            'diagnostics' => $writer->diagnostics(),
        ];
    }

    /**
     * The manifest of an imported metalanguage, read off its row.
     *
     * @throws \RuntimeException  When this site has no such language.
     */
    private function manifestFor(string $key, string $version): PackageManifest
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['lang_key', 'version', 'manifest']))
            ->from($db->quoteName(Metalanguages::TABLE))
            ->where($db->quoteName('version') . ' = :version')
            ->bind(':version', $version);

        $rows = $db->setQuery($query)->loadObjectList() ?: [];

        // Matched here rather than in the query. A chunk names the LionWeb
        // language - `jcb` - and a package is filed under the language's
        // *name* - `JCB`. Since format 5 a manifest says the first outright,
        // so the two can be compared rather than assumed to be each other in
        // a different case.
        //
        // The case-insensitive match on the row's key stays for every package
        // built before that, ER1 and Testlang among them. It is a guess, and
        // it is why a language whose key was not a case-variant of its name
        // could not be found at all - but refusing those packages would be
        // refusing languages that already work.
        // Either spelling, because the two callers hold different ones: an
        // import knows what the chunk said, `jcb`, and an export knows what
        // the project is bound to, `JCB`. Exact matches first, in both
        // spellings, and the case-insensitive one last.
        $looseMatch = null;

        foreach ($rows as $row) {
            $manifest = PackageManifest::fromJson((string) $row->manifest);

            if ($manifest->lionwebKey === $key || (string) $row->lang_key === $key) {
                return $manifest;
            }

            if ($looseMatch === null && strcasecmp((string) $row->lang_key, $key) === 0) {
                $looseMatch = $manifest;
            }
        }

        // Nothing said it outright. Every package built before format 5 is
        // silent about its language's key, ER1 and Testlang among them, so a
        // chunk saying `er1` can only be matched to a row called `ER1` by
        // ignoring case - a guess, and the reason a language whose key is not
        // a case-variant of its name could not be found at all.
        if ($looseMatch !== null) {
            return $looseMatch;
        }

        throw new \RuntimeException(sprintf(
            'This chunk is written in %s %s, which is not imported on this site.'
            . ' Import that metalanguage first.',
            $key,
            $version === '' ? '(no version)' : $version
        ));
    }

    /**
     * Which declared language the root node is classified by.
     *
     * The classifier's metapointer says it outright; the declared list is the
     * fallback for a chunk that leaves it off.
     *
     * @param  string  $fallback  The first declared language.
     */
    private function languageOf(Chunk $chunk, string $root, string $fallback): string
    {
        $language = $chunk->nodes()[$root]['classifier']['language'] ?? null;

        return \is_string($language) && $language !== '' ? $language : $fallback;
    }

    /**
     * What to call the project.
     *
     * The root node's id, which is what the exporter made of whatever the
     * model was called where it came from - `hello-world` for the blueprint of
     * that name. A model carries no opinion about what the thing editing it
     * should be called, so this is a starting point rather than an answer, and
     * renaming it is the first thing the edit screen offers.
     */
    private function nameFor(?string $root, string $language): string
    {
        $name = trim((string) preg_replace('/[^A-Za-z0-9]+/', ' ', (string) $root));

        return $name === '' ? $language . ' model' : $name;
    }

    /**
     * @param  array<string, mixed>  $converted
     */
    private function encode(array $converted): string
    {
        $json = json_encode($converted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException(
                'The converted model could not be encoded: ' . json_last_error_msg()
            );
        }

        return $json;
    }
}
