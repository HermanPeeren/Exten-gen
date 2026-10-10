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

use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Gen\Core\Rule\Rule;
use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A generator's parents, and the rules it runs because of them: step 5.9.
 *
 * Language ancestry (4.5) lets LedgerLang derive from ER1: it adds concepts and
 * may not remove or rename one. Generator ancestry is the same relation one
 * level up. A generator for LedgerLang extends the Joomla 6 generator for ER1:
 * it runs every one of the parent's rules, and adds rules for the concepts the
 * derived language added.
 *
 * - **Adding** a rule is the point. Its id is new, and it runs after the
 *   parent's rules of the same group, because the groups slice one list in order.
 * - **Overriding** a rule is allowed, by giving it the parent's id. It takes the
 *   parent rule's place, so it runs where that one ran.
 * - **Removing** a rule is not possible: there is no way to say it. A child that
 *   needs fewer files than its parent is not a child of that parent.
 *
 * Like `Ancestry` in the library, it takes a resolver rather than a catalogue,
 * so the walk - the cycle, the missing parent, the parent for another target -
 * is testable without a database.
 *
 * @since  1.6.0
 */
final class GeneratorAncestry
{
    /**
     * @param  \Closure(string): ?GeneratorEntry  $resolve  A generator by the id `extends` names.
     *
     * @since  1.6.0
     */
    public function __construct(private readonly \Closure $resolve)
    {
    }

    /**
     * The generator's parents, nearest first.
     *
     * @param  GeneratorEntry  $entry  The child.
     *
     * @return GeneratorEntry[]
     *
     * @throws \RuntimeException  When a parent is missing, is for another target, or the chain is a cycle.
     *
     * @since  1.6.0
     */
    public function of(GeneratorEntry $entry): array
    {
        $parents = [];
        $seen    = [$entry->id => true];
        $current = $entry;

        while ($current->extends !== '') {
            $parent = ($this->resolve)($current->extends);

            if ($parent === null) {
                throw new \RuntimeException(
                    'The generator ' . $current->name . ' extends "' . $current->extends . '", which this site has not got.'
                );
            }

            if (isset($seen[$parent->id])) {
                throw new \RuntimeException(
                    'The generator ' . $entry->name . ' extends itself, through ' . $parent->name . '.'
                );
            }

            if ($parent->target !== $entry->target) {
                throw new \RuntimeException(
                    'The generator ' . $current->name . ' is for ' . $entry->target . ' and extends ' . $parent->name
                    . ', which is for ' . $parent->target . '.'
                );
            }

            $seen[$parent->id] = true;
            $parents[]         = $parent;
            $current           = $parent;
        }

        return $parents;
    }

    /**
     * Every rule the generator runs: its parents' rules, then its own.
     *
     * @param  GeneratorEntry  $entry  The generator.
     *
     * @throws \RuntimeException  As `of()` does, and when the root has no rules to inherit.
     *
     * @since  1.6.0
     */
    public function rules(GeneratorEntry $entry): RuleSet
    {
        $line  = array_reverse([$entry, ...$this->of($entry)]);
        $rules = self::own(array_shift($line));

        foreach ($line as $child) {
            $rules = self::compose($rules, self::own($child));
        }

        return $rules;
    }

    /**
     * Whether every generator in the line applies to a project with this language ancestry.
     *
     * The child's language has to be in the project's ancestry, as before, and
     * so does each parent's: the parent's rules run too, and they read the
     * parent's language. For LedgerLang that is LedgerLang and ER1, which a
     * LedgerLang project has. A line that cannot be walked applies to nothing.
     *
     * @param  GeneratorEntry       $entry     The generator.
     * @param  MetalanguageEntry[]  $ancestry  The project's language and its ancestors.
     *
     * @since  1.6.0
     */
    public function appliesTo(GeneratorEntry $entry, array $ancestry): bool
    {
        try {
            $line = [$entry, ...$this->of($entry)];
        } catch (\RuntimeException) {
            return false;
        }

        foreach ($line as $generator) {
            if (!$generator->appliesTo($ancestry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A parent's rules with a child's laid over them.
     *
     * A child rule with a parent rule's id takes its place; any other child
     * rule is added at the end.
     *
     * @param  RuleSet  $parent  What is inherited.
     * @param  RuleSet  $child   What the child says.
     *
     * @since  1.6.0
     */
    public static function compose(RuleSet $parent, RuleSet $child): RuleSet
    {
        $own = [];

        foreach ($child as $rule) {
            $own[$rule->id] = $rule;
        }

        $rules = [];

        foreach ($parent as $rule) {
            $rules[] = $own[$rule->id] ?? $rule;

            unset($own[$rule->id]);
        }

        return new RuleSet([...$rules, ...array_values($own)]);
    }

    /**
     * The ids of the parent's rules a child replaces.
     *
     * @param  RuleSet  $parent  What is inherited.
     * @param  RuleSet  $child   What the child says.
     *
     * @return string[]
     *
     * @since  1.6.0
     */
    public static function overrides(RuleSet $parent, RuleSet $child): array
    {
        $ids = array_map(static fn (Rule $rule): string => $rule->id, iterator_to_array($parent));

        return array_values(array_filter(
            array_map(static fn (Rule $rule): string => $rule->id, iterator_to_array($child)),
            static fn (string $id): bool => \in_array($id, $ids, true)
        ));
    }

    /**
     * A generator's own rules, without its parents'.
     *
     * A target's own generator has its rules in the component's rule file;
     * every other one carries them.
     *
     * @param  GeneratorEntry  $entry  The generator.
     *
     * @throws \RuntimeException  When there are none to read.
     *
     * @since  1.6.0
     */
    public static function own(GeneratorEntry $entry): RuleSet
    {
        if ($entry->rules !== '') {
            return RuleSet::fromJson($entry->rules);
        }

        if ($entry->builtIn && $entry->target === 'joomla6') {
            return RuleSet::fromFile(RuleDrivenGenerator::defaultRuleFile());
        }

        throw new \RuntimeException(
            'The generator ' . $entry->name . ' is written as code rather than rules, so nothing can extend it.'
        );
    }
}
