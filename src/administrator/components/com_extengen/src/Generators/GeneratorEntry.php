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

use Yepr\Component\Extengen\Administrator\Generator\Imported\GeneratorPackage;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Gen\Core\Target\TargetInterface;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One generator this site can run: step 5.4.
 *
 * Either built in - one per target, the code this component ships - or imported
 * from Gen-gen, which is a rule set for one of those targets. Both are written
 * for one metalanguage, and a project may only be generated with a generator
 * whose language is in the project's ancestry.
 *
 * Since 5.9 a generator may extend another: it runs every rule of its parent
 * plus its own. The component ships one such generator, for LedgerLang, and an
 * imported one may say `extends` in its manifest. See `GeneratorAncestry`.
 *
 * @since  1.3.0
 */
final class GeneratorEntry
{
    /**
     * What an imported generator's id starts with. A built-in one's id is its target's.
     *
     * A dot, because the id travels in a URL as a `cmd` and Joomla's `cmd`
     * filter keeps letters, digits, dots, hyphens and underscores only.
     *
     * @since  1.3.0
     */
    public const IMPORTED = 'imported.';

    /**
     * @since  1.3.0
     */
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $target,
        public readonly string $targetLabel,
        public readonly string $metalanguageKey,
        public readonly string $metalanguageVersion,
        public readonly bool $builtIn,
        public readonly int $ruleCount,
        public readonly string $rules = '',
        public readonly string $imported = '',
        public readonly int $rowId = 0,
        public readonly bool $published = true,
        public readonly string $extends = ''
    ) {
    }

    /**
     * A generator read from a package, before it is stored: step 5.9.
     *
     * Two uses. A package this component ships becomes a generator that counts
     * as built in: it cannot be removed, and its id is its key rather than
     * `imported.<key>`. It is read with the same reader an import uses, so a
     * shipped generator is held to everything an imported one is. And an
     * import is walked as the entry it would become, before it is written.
     *
     * @param  GeneratorPackage  $package      The manifest and its rules.
     * @param  string            $targetLabel  The label of the target it names.
     * @param  bool              $builtIn      Shipped with this component, rather than imported.
     *
     * @since  1.6.0
     */
    public static function fromPackage(GeneratorPackage $package, string $targetLabel, bool $builtIn): self
    {
        return new self(
            ($builtIn ? '' : self::IMPORTED) . $package->key,
            $package->name,
            $package->target,
            $targetLabel,
            $package->metalanguageKey ?: RuleDrivenGenerator::LANGUAGE,
            $package->metalanguageVersion,
            $builtIn,
            \count($package->rules),
            $package->rules->toJson(),
            '',
            0,
            true,
            $package->extends
        );
    }

    /**
     * A target's own generator: the code this component ships.
     *
     * Every built-in target reads ER1. That is what `RuleDrivenGenerator::LANGUAGE`
     * says for the rule-driven one, and the WordPress and Drupal emitters read
     * the same model by the same names.
     *
     * @since  1.3.0
     */
    public static function builtIn(TargetInterface $target): self
    {
        return new self(
            $target->id(),
            $target->label(),
            $target->id(),
            $target->label(),
            RuleDrivenGenerator::LANGUAGE,
            '',
            true,
            0
        );
    }

    /**
     * An imported generator, from its row.
     *
     * @param  object  $row          A row of `#__extengen_generators`.
     * @param  string  $targetLabel  The label of the target it names, or its id when this site has no such target.
     *
     * @since  1.3.0
     */
    public static function fromRow(object $row, string $targetLabel): self
    {
        $rules = (string) ($row->rules ?? '');
        $count = 0;

        if ($rules !== '') {
            $decoded = json_decode($rules, true);
            $count   = \is_array($decoded) ? \count($decoded['rules'] ?? $decoded) : 0;
        }

        return new self(
            self::IMPORTED . (string) ($row->gen_key ?? ''),
            (string) ($row->name ?? ''),
            (string) ($row->target ?? ''),
            $targetLabel,
            (string) ($row->metalanguage_key ?? '') ?: RuleDrivenGenerator::LANGUAGE,
            (string) ($row->metalanguage_version ?? ''),
            false,
            $count,
            $rules,
            (string) ($row->imported ?? ''),
            (int) ($row->id ?? 0),
            (int) ($row->published ?? 1) === 1,
            self::extendsOf((string) ($row->manifest ?? ''))
        );
    }

    /**
     * Which generator an imported one extends, out of the manifest column: step 5.9.
     *
     * The column already holds the manifest's other half as JSON, so `extends`
     * travels there and the table does not change. A row written before 5.9
     * has no `extends` in it, which reads as extending nothing - true.
     *
     * @param  string  $manifest  The stored JSON.
     *
     * @since  1.6.0
     */
    private static function extendsOf(string $manifest): string
    {
        $decoded = $manifest === '' ? null : json_decode($manifest, true);

        return \is_array($decoded) ? trim((string) ($decoded['extends'] ?? '')) : '';
    }

    /**
     * Whether a project written in a language with this ancestry may be generated with this.
     *
     * The ancestry includes the language itself. A generator naming a version
     * wants that version; one naming none takes any, which is how every
     * built-in generator works - a minor version of ER1 adds and never removes.
     *
     * @param  MetalanguageEntry[]  $ancestry  The project's language and its ancestors.
     *
     * @since  1.3.0
     */
    public function appliesTo(array $ancestry): bool
    {
        foreach ($ancestry as $language) {
            if (
                $language->key === $this->metalanguageKey
                && ($this->metalanguageVersion === '' || $language->version === $this->metalanguageVersion)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * How the generator is named where somebody picks one.
     *
     * @since  1.3.0
     */
    public function label(): string
    {
        return $this->builtIn ? $this->name : $this->name . ' (' . $this->targetLabel . ')';
    }
}
