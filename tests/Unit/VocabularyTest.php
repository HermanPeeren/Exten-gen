<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Core\Rule\Vocabulary;

/**
 * What this target lets a rule say, published as data.
 *
 * The registries are the truth, and they are PHP. Gen-gen has to offer these
 * names as choices without loading Exten-gen, so they are written out beside
 * the rule file - and written out by a script, because a list maintained in two
 * places is a list that will disagree with itself. The disagreement would show
 * up as a form offering a derivation nobody registered, which is the kind of
 * thing that is only noticed by whoever picks it.
 */
final class VocabularyTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function path(): string
    {
        return $this->root()
            . '/src/administrator/components/com_extengen/src/Generator/Rules/joomla6.vocabulary.json';
    }

    private function vocabulary(): Vocabulary
    {
        return Vocabulary::fromFile($this->path());
    }

    /**
     * The committed file is what the script writes.
     *
     * Without this the generation is a suggestion: somebody registers a
     * derivation, forgets to run it, and the editor keeps offering yesterday's
     * list.
     */
    public function testTheCommittedFileIsWhatTheScriptGenerates(): void
    {
        $committed = (string) file_get_contents($this->path());

        exec('php ' . escapeshellarg($this->root() . '/build/vocabulary.php') . ' 2>&1', $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));

        $this->assertSame(
            $committed,
            (string) file_get_contents($this->path()),
            'The vocabulary is out of date. Run php build/vocabulary.php and commit the result.'
        );
    }

    /**
     * Everything the rule set names is in the vocabulary.
     *
     * `RuleSetTest` asks the same question of the live registries. This asks it
     * of the published descriptor, which is what an editor will have - and the
     * two can only give different answers if the descriptor is stale, which is
     * the test above.
     */
    public function testTheRuleSetSaysNothingTheVocabularyDoesNotAllow(): void
    {
        $problems = $this->vocabulary()->problems(new RuleSet(RuleDrivenGenerator::allRules()));

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function testItNamesThisTarget(): void
    {
        $this->assertSame('joomla6', $this->vocabulary()->target);
    }

    /**
     * Template identifiers are spelled the way a rule spells them.
     *
     * Relative to the template set and forward-slashed, so a descriptor written
     * on Windows is the one committed and the one a rule can be checked
     * against.
     */
    public function testTemplatesAreSpelledAsARuleSpellsThem(): void
    {
        foreach ($this->vocabulary()->templates as $template) {
            $this->assertStringNotContainsString('\\', $template, $template . ' has a Windows separator.');
            $this->assertStringStartsNotWith('/', $template, $template . ' is not relative.');
        }
    }

    /**
     * It lists the template set as it is, including the one file in it that
     * nothing renders - the GPL2 text the set was copied in with, which
     * `TemplateReachabilityTest` already accounts for. A directory listing that
     * quietly omitted things would be a second place to maintain.
     */
    public function testItListsTheWholeTemplateSet(): void
    {
        $root = $this->root()
            . '/src/administrator/components/com_extengen/generator_templates/Joomla6/';

        foreach ($this->vocabulary()->templates as $template) {
            $this->assertFileExists($root . $template);
        }

        $this->assertContains(
            'component/administrator/components/com_componentname/src/Table/Table.php.twig',
            $this->vocabulary()->templates
        );
    }
}
