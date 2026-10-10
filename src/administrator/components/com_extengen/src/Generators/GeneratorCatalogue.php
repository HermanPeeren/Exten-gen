<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generators;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Yepr\Component\Extengen\Administrator\Generator\Imported\GeneratorPackage;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Component\Extengen\Administrator\Generator\Target\RuleDrivenTarget;
use Yepr\Gen\Core\Target\TargetInterface;
use Yepr\Gen\Core\Target\TargetRegistry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Every generator this site can run, built in or imported: step 5.4.
 *
 * The built-in ones are the targets' own code, one per target, and are never
 * stored. The imported ones are Gen-gen packages: a rule set for one of those
 * targets, kept in `#__extengen_generators`.
 *
 * Since 5.9 there is a third kind between the two: a package this component
 * ships, read like an import and listed like a built-in. The LedgerLang
 * generator is one. It extends the Joomla 6 generator, which is the other thing
 * 5.9 added: any generator but a code-only target may be extended, and
 * `GeneratorAncestry` works out what the child runs.
 *
 * @since  1.3.0
 */
final class GeneratorCatalogue
{
    /**
     * The table the imported generators are kept in.
     *
     * @since  1.3.0
     */
    public const TABLE = '#__extengen_generators';

    /**
     * The generator packages this component ships, beside its rule file: step 5.9.
     *
     * @var string[]
     *
     * @since  1.6.0
     */
    public const SHIPPED = [];

    /**
     * @param  DatabaseInterface  $database      Where the imported generators are.
     * @param  TargetRegistry     $targets       What this site can generate into.
     * @param  string             $ruleCacheDir  Where an imported rule set is written as a file to be run.
     *
     * @since  1.3.0
     */
    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly TargetRegistry $targets,
        private readonly string $ruleCacheDir
    ) {
    }

    /**
     * Every generator: the built-in ones in the registry's order, then the imported ones by name.
     *
     * @return GeneratorEntry[]
     *
     * @since  1.3.0
     */
    public function all(): array
    {
        $entries = [];

        foreach ($this->targets as $target) {
            $entries[] = GeneratorEntry::builtIn($target);
        }

        foreach (self::shippedPackages() as $package) {
            if ($this->targets->has($package->target)) {
                $entries[] = GeneratorEntry::fromPackage($package, $this->targets->get($package->target)->label(), true);
            }
        }

        $query = $this->database->getQuery(true)
            ->select('*')
            ->from($this->database->quoteName(self::TABLE))
            ->order($this->database->quoteName('name'));

        foreach ($this->database->setQuery($query)->loadObjectList() ?: [] as $row) {
            $target    = (string) $row->target;
            $entries[] = GeneratorEntry::fromRow(
                $row,
                $this->targets->has($target) ? $this->targets->get($target)->label() : $target
            );
        }

        return $entries;
    }

    /**
     * One generator by id, or null.
     *
     * @since  1.3.0
     */
    public function find(string $id): ?GeneratorEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The generators a project in a language with this ancestry may be generated with.
     *
     * Published ones only, and only those whose target this site has.
     *
     * @param  \Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry[]  $ancestry  The language and its ancestors.
     *
     * @return GeneratorEntry[]
     *
     * @since  1.3.0
     */
    public function forLanguage(array $ancestry): array
    {
        $entries = $this->all();
        $walk    = self::walkOver($entries);

        return array_values(array_filter(
            $entries,
            fn (GeneratorEntry $entry): bool => $entry->published
                && $this->targets->has($entry->target)
                && $walk->appliesTo($entry, $ancestry)
        ));
    }

    /**
     * Whether a generator, and every generator it extends, applies to this language ancestry: step 5.9.
     *
     * @param  GeneratorEntry                                     $entry     The generator.
     * @param  \Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry[]  $ancestry  The language and its ancestors.
     *
     * @since  1.6.0
     */
    public function appliesTo(GeneratorEntry $entry, array $ancestry): bool
    {
        return $this->ancestry()->appliesTo($entry, $ancestry);
    }

    /**
     * The walk over `extends`, resolving against this catalogue: step 5.9.
     *
     * `extends` names a generator as its manifest would: a target's id for a
     * built-in one, a key for a shipped or an imported one.
     *
     * @since  1.6.0
     */
    public function ancestry(): GeneratorAncestry
    {
        return self::walkOver($this->all());
    }

    /**
     * The walk over `extends`, resolving against a list of generators.
     *
     * @param  GeneratorEntry[]  $entries  What a parent may be.
     *
     * @since  1.6.0
     */
    private static function walkOver(array $entries): GeneratorAncestry
    {
        return new GeneratorAncestry(static function (string $id) use ($entries): ?GeneratorEntry {
            foreach ($entries as $entry) {
                if ($entry->id === $id || $entry->id === GeneratorEntry::IMPORTED . $id) {
                    return $entry;
                }
            }

            return null;
        });
    }

    /**
     * The packages this component ships, read with the import's own reader.
     *
     * @return GeneratorPackage[]
     *
     * @since  1.6.0
     */
    public static function shippedPackages(): array
    {
        $directory = \dirname(RuleDrivenGenerator::defaultRuleFile());

        return array_map(
            static fn (string $manifest): GeneratorPackage => GeneratorPackage::fromFile($directory . '/' . $manifest),
            self::SHIPPED
        );
    }

    /**
     * The target to run for a generator: its own, with the imported rules if it has any.
     *
     * @throws \RuntimeException  When this site cannot run it.
     *
     * @since  1.3.0
     */
    public function target(GeneratorEntry $entry): TargetInterface
    {
        if (!$this->targets->has($entry->target)) {
            throw new \RuntimeException('This site cannot generate into ' . $entry->target . '.');
        }

        $target = $this->targets->get($entry->target);

        if ($entry->builtIn && $entry->extends === '') {
            return $target;
        }

        if (!$target instanceof RuleDrivenTarget) {
            throw new \RuntimeException($target->label() . ' has no rules for a generator to replace.');
        }

        return $target->withRules($this->ruleFile($entry));
    }

    /**
     * Import a Gen-gen package, replacing a generator of the same key.
     *
     * Nothing is written until the package has been read and its rules checked,
     * so a refused import leaves nothing behind.
     *
     * @param  string  $zip  The uploaded file.
     *
     * @throws \RuntimeException  With a sentence a person can act on.
     *
     * @since  1.3.0
     */
    public function import(string $zip): GeneratorEntry
    {
        $package = GeneratorPackage::fromZip($zip);

        if (!$this->targets->has($package->target)) {
            throw new \RuntimeException(
                'The generator is for ' . $package->target . ', and this site generates into '
                . implode(', ', $this->targets->ids()) . '.'
            );
        }

        $target = $this->targets->get($package->target);

        if (!$target instanceof RuleDrivenTarget) {
            throw new \RuntimeException(
                $target->label() . ' is written as code rather than rules, so there is nothing for an imported generator to replace.'
            );
        }

        $problems = $package->problems($target->vocabulary(), $target->rulePrefixes());

        // What it extends has to be here, for the same target, and not lead back
        // to itself. Walked as the entry it would become, so a re-import that
        // makes a generator extend its own child is refused before it is stored.
        if ($package->extends !== '') {
            try {
                $this->ancestry()->of(GeneratorEntry::fromPackage($package, $target->label(), false));
            } catch (\RuntimeException $e) {
                $problems[] = $e->getMessage();
            }
        }

        if ($problems !== []) {
            throw new \RuntimeException(
                'The generator was not imported: ' . implode(' ', \array_slice($problems, 0, 5))
                . (\count($problems) > 5 ? ' (and ' . (\count($problems) - 5) . ' more)' : '')
            );
        }

        $row = (object) [
            'gen_key'              => $package->key,
            'name'                 => $package->name,
            'target'               => $package->target,
            'metalanguage_key'     => $package->metalanguageKey,
            'metalanguage_version' => $package->metalanguageVersion,
            'rules'                => $package->rules->toJson(),
            'manifest'             => json_encode(
                ['groups' => $package->groups] + ($package->extends === '' ? [] : ['extends' => $package->extends]),
                \JSON_UNESCAPED_SLASHES
            ),
            'imported'             => gmdate('Y-m-d H:i:s'),
            'published'            => 1,
        ];

        $existing = $this->rowIdOf($package->key);

        if ($existing > 0) {
            $row->id = $existing;
            $this->database->updateObject(self::TABLE, $row, 'id');
        } else {
            $this->database->insertObject(self::TABLE, $row, 'id');
        }

        return GeneratorEntry::fromRow($row, $target->label());
    }

    /**
     * Forget an imported generator.
     *
     * Not one another generator extends: the child would stop working, and
     * nothing on the screen would say why.
     *
     * @throws \RuntimeException  When another generator extends it.
     *
     * @since  1.3.0
     */
    public function remove(int $rowId): void
    {
        $ancestry = $this->ancestry();

        foreach ($this->all() as $other) {
            try {
                $parents = $ancestry->of($other);
            } catch (\RuntimeException) {
                continue;
            }

            foreach ($parents as $parent) {
                if (!$parent->builtIn && $parent->rowId === $rowId) {
                    throw new \RuntimeException(
                        'The generator ' . $parent->name . ' was not removed: ' . $other->name . ' extends it.'
                    );
                }
            }
        }

        $query = $this->database->getQuery(true)
            ->delete($this->database->quoteName(self::TABLE))
            ->where($this->database->quoteName('id') . ' = :id')
            ->bind(':id', $rowId, ParameterType::INTEGER);

        $this->database->setQuery($query)->execute();
    }

    /**
     * The row id of an imported generator by key, or 0.
     *
     * @since  1.3.0
     */
    private function rowIdOf(string $key): int
    {
        $query = $this->database->getQuery(true)
            ->select($this->database->quoteName('id'))
            ->from($this->database->quoteName(self::TABLE))
            ->where($this->database->quoteName('gen_key') . ' = :key')
            ->bind(':key', $key);

        return (int) $this->database->setQuery($query)->loadResult();
    }

    /**
     * The rules a generator runs as a file, which is what a rule-driven generator reads.
     *
     * Its own rules, or since 5.9 its parents' with its own laid over them.
     * Named by a hash of the rules, so a re-import is a new file rather than a
     * stale one, and a file that is already there is used as it is.
     *
     * @since  1.3.0
     */
    private function ruleFile(GeneratorEntry $entry): string
    {
        if ($entry->rules === '') {
            throw new \RuntimeException('The generator ' . $entry->name . ' has no rules stored.');
        }

        $rules = $entry->extends === '' ? $entry->rules : $this->ancestry()->rules($entry)->toJson();
        $name  = str_starts_with($entry->id, GeneratorEntry::IMPORTED)
            ? substr($entry->id, \strlen(GeneratorEntry::IMPORTED))
            : $entry->id;

        $path = $this->ruleCacheDir . '/' . $name . '-' . sha1($rules) . '.rules.json';

        if (!is_file($path)) {
            if (!is_dir($this->ruleCacheDir) && !mkdir($this->ruleCacheDir, 0755, true) && !is_dir($this->ruleCacheDir)) {
                throw new \RuntimeException('Cannot create ' . $this->ruleCacheDir . '.');
            }

            file_put_contents($path, $rules);
        }

        return $path;
    }
}
