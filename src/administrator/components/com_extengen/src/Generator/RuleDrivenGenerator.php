<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Generator;

use Yepr\Component\Extengen\Administrator\Generator\Rules\Joomla6Derivations;
use Yepr\Component\Extengen\Administrator\Generator\Rules\Joomla6Selectors;
use Yepr\Gen\Core\Rule\Rule;
use Yepr\Gen\Core\Rule\RuleEngine;
use Yepr\Gen\Core\Rule\RuleSet;

/**
 * A generator whose files come from the rule set rather than from its own code.
 *
 * What is left in a concrete generator after this is only what rules cannot
 * express: the sql statements, the form XML, the language files. Those are
 * emitters - they assemble a file rather than render a template - and they stay
 * code deliberately.
 *
 * Each generator runs the slice of the shared rule file whose ids begin with
 * its prefix. One file rather than seven keeps the whole mapping readable in
 * one place; the prefix keeps each slice running where it always ran, which
 * matters because the language files are written last and collect their strings
 * in the order the templates rendered.
 *
 * @since  1.1.0
 */
abstract class RuleDrivenGenerator extends Generator
{
    /**
     * Parsed rule sets, by file, once per process.
     *
     * Keyed by path rather than held as one, because a generator produced by
     * Gen-gen carries its own rule file and has to be runnable beside the
     * committed one - which is how 2.3 checks that a modelled generator and a
     * hand-written one produce the same bytes.
     *
     * @var    array<string, RuleSet>
     * @since  1.1.0
     */
    private static array $rules = [];

    /**
     * Which rules this generator runs: the prefix of their ids.
     *
     * @return  string
     *
     * @since   1.1.0
     */
    abstract public function rulePrefix(): string;

    /**
     * Where this generator's rules are written down.
     *
     * The committed rule file, for every generator in this component. A
     * generated one overrides it and nothing else changes, which is the point:
     * the difference between a hand-written generator and a modelled one is a
     * path.
     *
     * @return  string
     *
     * @since   1.1.0
     */
    protected function ruleFile(): string
    {
        return self::defaultRuleFile();
    }

    /**
     * Files this generator produces that no rule can express.
     *
     * Overridden by the generators that also emit something - sql, forms,
     * language files - and by nothing else.
     *
     * @return  string[]  A log of what was produced.
     *
     * @since   1.1.0
     */
    protected function generateBeyondRules(): array
    {
        return [];
    }

    /**
     * Run this generator's rules, then whatever it emits by hand.
     *
     * @return  string[]  A log of what was produced.
     *
     * @since   1.1.0
     */
    protected function generateFiles(): array
    {
        $derivations = new Joomla6Derivations();

        $engine = new RuleEngine(
            $this->renderer,
            Joomla6Selectors::registry(),
            $derivations->registry()
        );

        $log = $engine->run(
            self::rules($this->ruleFile())->withPrefix($this->rulePrefix()),
            $this->AST,
            function (string $path, string $contents): void {
                // Through addFile, not the collection directly, so that two
                // rules deriving one path are counted rather than one of them
                // silently disappearing. A details page called Track and an
                // index page called Tracks still both give TracksModel.php.
                $this->addFile($path, $contents);
            }
        );

        return array_merge($derivations->warnings(), $log, $this->generateBeyondRules());
    }

    /**
     * The component's own rule file.
     *
     * @return  string
     *
     * @since   1.1.0
     */
    public static function defaultRuleFile(): string
    {
        return __DIR__ . '/Rules/joomla6.rules.json';
    }

    /**
     * A rule set, parsed once and shared by every generator in the run.
     *
     * @param   string  $path  The rule file.
     *
     * @return  RuleSet
     *
     * @since   1.1.0
     */
    private static function rules(string $path): RuleSet
    {
        return self::$rules[$path] ??= RuleSet::fromFile($path);
    }

    /**
     * Every rule in the committed set, for the tests that check it against the
     * template set.
     *
     * @return  Rule[]
     *
     * @since   1.1.0
     */
    public static function allRules(): array
    {
        return iterator_to_array(self::rules(self::defaultRuleFile()));
    }
}
