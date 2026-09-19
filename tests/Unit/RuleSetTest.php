<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Component\Extengen\Administrator\Generator\Rules\Joomla6Derivations;
use Yepr\Component\Extengen\Administrator\Generator\Rules\Joomla6Selectors;
use Yepr\Component\Extengen\Administrator\Generator\Target\Joomla6Target;
use Yepr\Gen\Core\Rule\Binding;
use Yepr\Gen\Core\Rule\Rule;
use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Core\Rule\RuleSetValidator;

/**
 * The rule file says what it can be checked to say.
 *
 * A rule set is data, and data has no compiler. Nothing about a typo in
 * `"derive": "adminPageForeing"` is visible until a generated file comes out
 * with a variable missing, in a project nobody has generated yet. These are the
 * checks that stand in for the compiler.
 *
 * Only one of them needs a model. The rest are answerable from the rule file,
 * the two registries and the template directory, which is what makes them worth
 * having: they fail on the commit that breaks them.
 */
final class RuleSetTest extends TestCase
{
    /**
     * @return Rule[]
     */
    private function rules(): array
    {
        return RuleDrivenGenerator::allRules();
    }

    private function templateRoot(): string
    {
        return \dirname(__DIR__, 2)
            . '/src/administrator/components/com_extengen/generator_templates/Joomla6/';
    }

    /**
     * Every selector and derivation a rule names is registered, every target
     * path can be built from what its rule binds, and nothing escapes the
     * package.
     */
    public function testTheRuleSetIsSound(): void
    {
        $validator = new RuleSetValidator(
            Joomla6Selectors::registry(),
            (new Joomla6Derivations())->registry()
        );

        $problems = $validator->problems(new RuleSet($this->rules()));

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Every template a rule names is in the template set.
     *
     * Twig throws for a missing template, but only for the rules a given model
     * happens to exercise. This asks about all of them.
     */
    public function testEveryTemplateExists(): void
    {
        $missing = [];

        foreach ($this->rules() as $rule) {
            if (!is_file($this->templateRoot() . $rule->template)) {
                $missing[] = $rule->id . ' -> ' . $rule->template;
            }

            foreach ($rule->bind as $name => $binding) {
                if ($binding->kind !== Binding::FRAGMENTS) {
                    continue;
                }

                if (!is_file($this->templateRoot() . $binding->template)) {
                    $missing[] = $rule->id . '/' . $name . ' -> ' . $binding->template;
                }
            }
        }

        $this->assertSame([], $missing, 'Templates a rule names but the set does not hold: ' . implode(', ', $missing));
    }

    /**
     * Every rule belongs to exactly one generator.
     *
     * A rule whose id matches no generator's prefix never runs, and nothing
     * else would say so - the file it would have produced is simply absent, and
     * "absent" is indistinguishable from "not wanted". A rule matching two
     * prefixes runs twice, which is worse: it usually produces the same bytes,
     * so it only shows up as a duplicate somewhere in a log.
     */
    public function testEveryRuleIsRunByExactlyOneGenerator(): void
    {
        $prefixes = [];

        $target = new Joomla6Target(\dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/generator_templates');

        foreach ($target->generators() as $generator) {
            if ($generator instanceof RuleDrivenGenerator) {
                $prefixes[] = $generator->rulePrefix();
            }
        }

        $this->assertNotEmpty($prefixes, 'No generator runs rules at all.');

        foreach ($this->rules() as $rule) {
            $matches = array_values(array_filter(
                $prefixes,
                static fn (string $prefix): bool => str_starts_with($rule->id, $prefix)
            ));

            $this->assertCount(
                1,
                $matches,
                'Rule ' . $rule->id . ' is run by ' . \count($matches) . ' generators: '
                . ($matches === [] ? 'none of ' . implode(', ', $prefixes) : implode(', ', $matches))
            );
        }
    }

    /**
     * No derivation is registered that no rule uses.
     *
     * The derivations are the part that stayed code, so they are the part that
     * rots quietly: a rule changes, the function it called is now unreachable,
     * and it sits there looking load-bearing. There is nothing else that would
     * notice.
     */
    public function testEveryDerivationIsUsed(): void
    {
        $used = [];

        foreach ($this->rules() as $rule) {
            foreach ($rule->bind as $binding) {
                if ($binding->kind === Binding::DERIVE || $binding->kind === Binding::FRAGMENTS) {
                    $used[(string) $binding->value] = true;
                }
            }
        }

        $registered = explode(', ', (new Joomla6Derivations())->registry()->names());
        $unused     = array_values(array_diff($registered, array_keys($used)));

        $this->assertSame([], $unused, 'Registered but used by no rule: ' . implode(', ', $unused));
    }

    /**
     * Every selector is used, for the same reason.
     */
    public function testEverySelectorIsUsed(): void
    {
        $used       = array_unique(array_map(static fn (Rule $rule): string => $rule->for, $this->rules()));
        $registered = explode(', ', Joomla6Selectors::registry()->names());
        $unused     = array_values(array_diff($registered, $used));

        $this->assertSame([], $unused, 'Registered but used by no rule: ' . implode(', ', $unused));
    }

    /**
     * The committed file is what the parser would write back.
     *
     * Gen-gen will edit this file through a form, which means reading it and
     * writing it out again. Anything the round trip loses is something an edit
     * would delete without saying so, and the time to find that out is now.
     */
    public function testTheFileSurvivesBeingReadAndWrittenBack(): void
    {
        $set = new RuleSet($this->rules());

        $this->assertSame(
            $set->toArray(),
            RuleSet::fromJson($set->toJson())->toArray()
        );
    }

    /**
     * Ids read as a path from generator to concern, which is what makes the
     * prefixes above a design rather than a convention nobody wrote down.
     */
    public function testIdsAreDottedAndLowerCase(): void
    {
        foreach ($this->rules() as $rule) {
            $this->assertMatchesRegularExpression(
                '/^[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)+$/',
                $rule->id,
                $rule->id . ' is not a dotted lower-case id.'
            );
        }
    }
}
