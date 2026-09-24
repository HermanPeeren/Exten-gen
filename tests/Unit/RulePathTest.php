<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Component\Extengen\Tests\Support\ShippedLanguage;
use Yepr\Gen\Core\Package\PackageReader;

/**
 * Every name the rules walk is a name something declares: the last of 4.5.
 *
 * `AncestryCheck` refuses a derived language that removes or renames a concept
 * or a feature, and the argument for why that is enough went: a rule binds by
 * path, a path walks by name, so if every name survives every path survives.
 * The argument has a hole in it, which is that nothing had checked the paths
 * only walk by names **the language declares**. A rule reaching somewhere else
 * entirely would keep working for ER1 and break for a derived language without
 * either of them having done anything wrong.
 *
 * So this walks the other way: out of the rule file and the vocabulary, and
 * into what the shipped language says it has.
 *
 * **It found one immediately, and it is not a bug.** The path `name` is the
 * project's own name - the Joomla chrome, which 3.2 decided is not derivable
 * from a language and which `project_chrome.xml` declares instead. A generated
 * component's manifest needs it, so a rule reads it, and it will never be in
 * ER1. That is a dependency on something outside the language, and the point of
 * this file is that it is now a *declared* one: the chrome form is read here
 * rather than a list of exceptions being written, so a rule that started
 * reading `catid` would pass and a rule that started reading `nonsense` would
 * not.
 *
 * **What this does not reach.** A derivation is PHP - `$node->entity_name` in
 * `Joomla6Derivations` - and no amount of reading JSON finds those. Twenty-three
 * derivations read the model directly, and a language that renamed what they
 * read would break them exactly as silently as it would have broken a path.
 * That is the next thing, and it is named in the plan rather than implied to be
 * covered here.
 *
 * @since  1.4.0
 */
final class RulePathTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    /**
     * Every name the shipped language declares, feature or concept.
     *
     * @return array{features: array<string, true>, concepts: array<string, true>}
     */
    private function language(): array
    {
        $manifest = PackageReader::fromZip(ShippedLanguage::package())->manifest();

        $features = [];
        $concepts = [];

        foreach ($manifest->concepts as $concept) {
            $concepts[$concept['name']] = true;

            $this->assertArrayHasKey(
                'features',
                $concept,
                $concept['name'] . ' lists no features, so this test would check nothing about it.'
            );

            foreach ($concept['features'] as $feature) {
                $features[$feature['name']] = true;
            }
        }

        return ['features' => $features, 'concepts' => $concepts];
    }

    /**
     * Every field the Joomla half of a project carries.
     *
     * Read from the form rather than listed here, so that the set of things a
     * rule may legitimately reach outside the language is the set that actually
     * exists, and moves when it moves.
     *
     * @return array<string, true>
     */
    private function chrome(): array
    {
        $form = simplexml_load_file(
            $this->root() . '/src/administrator/components/com_extengen/forms/project_chrome.xml'
        );

        $this->assertNotFalse($form);

        $fields = [];

        foreach ($form->xpath('//field[@name]') ?: [] as $field) {
            $fields[(string) $field['name']] = true;
        }

        $this->assertNotSame([], $fields, 'The chrome form declares nothing, so this checks nothing.');

        return $fields;
    }

    /**
     * Every step of every path a rule binds by.
     *
     * @return array<string, string>  step => the rule that walks it.
     */
    private function ruleSteps(): array
    {
        $rules = json_decode(
            (string) file_get_contents(RuleDrivenGenerator::defaultRuleFile()),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $steps = [];

        foreach ($rules as $rule) {
            foreach ($rule['bind'] ?? [] as $binding) {
                if (!isset($binding['path'])) {
                    continue;
                }

                foreach (explode('.', (string) $binding['path']) as $step) {
                    $steps[$step] = (string) $rule['id'];
                }
            }
        }

        return $steps;
    }

    /**
     * Every path a rule binds by walks names ER1 or the chrome declares.
     */
    public function testEveryRulePathWalksADeclaredName(): void
    {
        $declared = $this->language()['features'] + $this->chrome();
        $steps    = $this->ruleSteps();

        $this->assertGreaterThan(8, \count($steps), 'Hardly any path was read, so this checked nothing.');

        $unknown = [];

        foreach ($steps as $step => $rule) {
            if (!isset($declared[$step])) {
                $unknown[] = $rule . ' walks "' . $step . '"';
            }
        }

        $this->assertSame(
            [],
            $unknown,
            "These rules walk names neither the language nor the chrome declares:\n  "
            . implode("\n  ", $unknown)
        );
    }

    /**
     * And so does every selector path in the published vocabulary.
     *
     * A `contain` and a `follow` step name a feature; a `follow`'s `to` names a
     * concept. The vocabulary is what Gen-gen offers and what the engine walks,
     * so a step naming something the language has not got is a selector that
     * silently returns nothing - which is the failure 3.6 spent a step on.
     */
    public function testEverySelectorPathWalksADeclaredName(): void
    {
        $language  = $this->language();
        $declared  = $language['features'] + $this->chrome();

        $vocabulary = json_decode(
            (string) file_get_contents(
                \dirname(RuleDrivenGenerator::defaultRuleFile()) . '/joomla6.vocabulary.json'
            ),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $unknown = [];
        $checked = 0;

        foreach ($vocabulary['selectorPaths'] ?? [] as $selector => $path) {
            foreach ($path as $step) {
                foreach (['contain', 'follow'] as $kind) {
                    if (isset($step[$kind])) {
                        $checked++;

                        if (!isset($declared[$step[$kind]])) {
                            $unknown[] = $selector . ' ' . $kind . 's "' . $step[$kind] . '"';
                        }
                    }
                }

                if (isset($step['to'])) {
                    $checked++;

                    if (!isset($language['concepts'][$step['to']])) {
                        $unknown[] = $selector . ' follows to "' . $step['to'] . '", which is not a concept';
                    }
                }
            }
        }

        $this->assertGreaterThan(8, $checked, 'Hardly any step was read, so this checked nothing.');

        $this->assertSame(
            [],
            $unknown,
            "These selectors walk names the language has not got:\n  " . implode("\n  ", $unknown)
        );
    }

    /**
     * The chrome is the exception, and it is a small one.
     *
     * Stated so that it stays small. A rule file free to read anything the
     * Joomla half carries would be a rule file that quietly stopped being about
     * the language at all - and the whole of 4.5 rests on the language being
     * what a rule depends on.
     */
    public function testOnlyTheProjectsOwnNameComesFromTheChrome(): void
    {
        $features = $this->language()['features'];
        $borrowed = [];

        foreach (array_keys($this->ruleSteps()) as $step) {
            if (!isset($features[$step])) {
                $borrowed[] = $step;
            }
        }

        $this->assertSame(['name'], $borrowed);
    }
}
