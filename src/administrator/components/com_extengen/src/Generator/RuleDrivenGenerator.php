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
use Yepr\Component\Extengen\Administrator\Reference\Er1;
use Yepr\Gen\Core\Rule\Vocabulary;
use Yepr\Gen\Core\Rule\Registry;
use Yepr\Gen\Core\Rule\DataSelectors;
use Yepr\Gen\Core\Reference\ReferenceIndex;
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
     * Which metalanguage the committed rules are written against: step 3.6.
     *
     * 3.6 made a selector a path rather than a closure, so `entities` is no
     * longer the word `datamodel` compiled into PHP. That is half the distance.
     * The other half is in the rule file itself and in `Joomla6Derivations`,
     * which are full of ER1 by name - `entity_name`, `page_type`, the shape of
     * a property - and no amount of data in the vocabulary changes what those
     * rules are about.
     *
     * So this is the honest statement of what is still true, written once where
     * something can act on it. `GenerateModel` refuses a project written in
     * another language rather than running these rules over a model they cannot
     * describe, which produced a component with empty views and no error.
     *
     * The version is deliberately not part of it. A language's minor version
     * adds concepts; a rule written for ER1 1.0 is still a rule about ER1 when
     * the language reaches 1.1, and refusing on the version would mean nobody
     * could ever ship one.
     *
     * @since  1.2.0
     */
    public const LANGUAGE = 'ER1';

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
     * Which source elements a rule may be written for: step 3.6.
     *
     * Read out of the target's own vocabulary, as paths through the model,
     * rather than out of a PHP class. `Joomla6Selectors::entities()` reached
     * into `$model->datamodel` - ER1's word for it - so a generator modelled
     * for another metalanguage could name that selector and get nothing back.
     * A path says what it means, in the descriptor that already says what a
     * rule may say.
     *
     * **The table comes from the project's own language.** `GenerateModel`
     * resolves it before the run and the screen refuses outright if it is not
     * the language these rules are written for, so by the time a selector
     * follows a reference the table is the right one rather than the only one.
     *
     * A vocabulary with no paths falls back to the closures, which is what a
     * target generating from something other than a modelled language looks
     * like. Nothing in this component is one; the branch is here so that being
     * one stays possible.
     *
     * @return  Registry
     *
     * @since   1.2.0
     */
    protected function selectors(): Registry
    {
        // Beside the component's *own* rule file, never beside `ruleFile()`. A
        // generated generator overrides the rules and is written to a temp
        // directory with nothing else in it, so resolving the vocabulary from
        // there found no file and stopped the run - which is what the
        // acceptance check in Gen-gen caught. The vocabulary belongs to the
        // target and not to whichever rule set is being run through it.
        $vocabulary = Vocabulary::fromFile(
            \dirname(self::defaultRuleFile()) . '/joomla6.vocabulary.json'
        );

        if ($vocabulary->paths() === []) {
            return Joomla6Selectors::registry();
        }

        // The table belongs to the language the project is written in, which
        // the screen resolves and puts in LanguageContext before the run.
        // Falling back to ER1's is for the caller that does not go through that
        // screen - Gen-gen's acceptance check runs this pipeline directly - and
        // is what every project here is written in anyway.
        return DataSelectors::registry(
            $vocabulary->paths(),
            LanguageContext::current() ?? ReferenceIndex::fromTable(Er1::TABLE)
        );
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
            $this->selectors(),
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
