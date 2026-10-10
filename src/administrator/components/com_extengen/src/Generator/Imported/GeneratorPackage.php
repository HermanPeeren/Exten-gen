<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Imported;

use Yepr\Gen\Core\Rule\RuleSet;
use Yepr\Gen\Core\Rule\Vocabulary;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A generator package from Gen-gen, read: step 5.4.
 *
 * Gen-gen writes a rule file and the classes that carry it, and since 5.4 a
 * `generator.json` saying what the package is: which target it generates for,
 * which metalanguage its rules are about, and where the rule file is inside
 * the zip. Gen-gen writes it and this reads it. Gen-gen depends on none of the
 * components it generates for, so the format cannot live in one place; instead
 * Gen-gen's suite reads its own packages with this class, which is what keeps
 * the writer from drifting away from the reader.
 *
 * **Only the rule file is read.** The classes in the package are wiring - 2.3
 * showed that a generated class adds nothing but the path to its rules - so an
 * imported generator runs this component's own classes over the imported rules
 * and no PHP from the package is ever loaded. The rules themselves are data: a
 * rule may only name selectors, derivations and templates the target publishes,
 * and its output path is interpolated, not rendered by Twig. That is what makes
 * an import safe to offer on a site where people other than the administrator
 * can generate.
 *
 * @since  1.3.0
 */
final class GeneratorPackage
{
    /**
     * The manifest's name inside the zip.
     *
     * @since  1.3.0
     */
    public const MANIFEST = 'generator.json';

    /**
     * The manifest format this reader understands.
     *
     * @since  1.3.0
     */
    public const FORMAT = 1;

    /**
     * @param  string                                                   $key                  Stable identity: re-importing the same key replaces it.
     * @param  string                                                   $name                 What a person reads.
     * @param  string                                                   $target               The target id it generates for.
     * @param  string                                                   $metalanguageKey      The language its rules are about, '' for the target's own.
     * @param  string                                                   $metalanguageVersion  That language's version, '' for any.
     * @param  RuleSet                                                  $rules                The rules.
     * @param  array<int, array{class: string, prefix: string, emits: bool}>  $groups      The groups the rules are sliced into.
     *
     * @since  1.3.0
     */
    private function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $target,
        public readonly string $metalanguageKey,
        public readonly string $metalanguageVersion,
        public readonly RuleSet $rules,
        public readonly array $groups
    ) {
    }

    /**
     * Read a package, or say why it is not one.
     *
     * @param  string  $zip  Path to the uploaded file.
     *
     * @throws \RuntimeException  With a sentence a person can act on.
     *
     * @since  1.3.0
     */
    public static function fromZip(string $zip): self
    {
        $archive = new \ZipArchive();

        if ($archive->open($zip) !== true) {
            throw new \RuntimeException('This is not a zip file.');
        }

        try {
            $manifest = $archive->getFromName(self::MANIFEST);

            if ($manifest === false) {
                throw new \RuntimeException(
                    'There is no ' . self::MANIFEST . ' in this zip. Generate it again with the Generator Generator 0.4 or later.'
                );
            }

            $data = json_decode($manifest, true);

            if (!\is_array($data)) {
                throw new \RuntimeException(self::MANIFEST . ' is not valid JSON.');
            }

            $rulesPath = (string) ($data['rules'] ?? '');
            $rules     = $rulesPath === '' ? false : $archive->getFromName($rulesPath);

            if ($rules === false) {
                throw new \RuntimeException(
                    'The rule file ' . self::MANIFEST . ' names, "' . $rulesPath . '", is not in the zip.'
                );
            }

            return self::fromManifest($data, $rules);
        } finally {
            $archive->close();
        }
    }

    /**
     * Read a manifest and the rule file it names.
     *
     * @param  array<string, mixed>  $data   The decoded manifest.
     * @param  string                $rules  The rule file's contents.
     *
     * @throws \RuntimeException  With a sentence a person can act on.
     *
     * @since  1.3.0
     */
    public static function fromManifest(array $data, string $rules): self
    {
        if ((int) ($data['format'] ?? 0) !== self::FORMAT) {
            throw new \RuntimeException(
                'This package is format ' . (int) ($data['format'] ?? 0) . '; this Extension Generator reads format '
                . self::FORMAT . '.'
            );
        }

        $name   = trim((string) ($data['name'] ?? ''));
        $target = trim((string) ($data['target'] ?? ''));

        if ($name === '' || $target === '') {
            throw new \RuntimeException(self::MANIFEST . ' must say the generator\'s name and its target.');
        }

        $key = self::slug((string) ($data['key'] ?? '') ?: $name);

        try {
            $ruleSet = RuleSet::fromJson($rules);
        } catch (\Throwable $e) {
            throw new \RuntimeException('The rule file cannot be read: ' . $e->getMessage(), 0, $e);
        }

        $language = \is_array($data['metalanguage'] ?? null) ? $data['metalanguage'] : [];
        $groups   = [];

        foreach (\is_array($data['groups'] ?? null) ? $data['groups'] : [] as $group) {
            if (!\is_array($group)) {
                continue;
            }

            $groups[] = [
                'class'  => (string) ($group['class'] ?? ''),
                'prefix' => (string) ($group['prefix'] ?? ''),
                'emits'  => (bool) ($group['emits'] ?? false),
            ];
        }

        return new self(
            $key,
            $name,
            $target,
            trim((string) ($language['key'] ?? '')),
            trim((string) ($language['version'] ?? '')),
            $ruleSet,
            $groups
        );
    }

    /**
     * Everything that would make this generator unsafe or useless to run.
     *
     * Two checks, both before anything is stored. Every rule must name only
     * what the target's vocabulary publishes - which is also what keeps an
     * uploaded rule from naming a template that is not this component's. And
     * every rule must fall under a prefix one of the target's generators
     * claims: this component runs its own classes over imported rules, so a
     * rule under any other prefix would never run, and storing it would be
     * storing a promise nothing keeps.
     *
     * @param  Vocabulary  $vocabulary  What the target says a rule may name.
     * @param  string[]    $prefixes    The prefixes the target's generators claim.
     *
     * @return string[]  Empty when the generator can be imported.
     *
     * @since  1.3.0
     */
    public function problems(Vocabulary $vocabulary, array $prefixes): array
    {
        if (\count($this->rules) === 0) {
            return ['The generator has no rules.'];
        }

        $problems = $vocabulary->problems($this->rules);

        foreach ($this->rules as $rule) {
            $claimed = false;

            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($rule->id, $prefix)) {
                    $claimed = true;

                    break;
                }
            }

            if (!$claimed) {
                $problems[] = 'rule ' . $rule->id . ': no generator of ' . $this->target
                    . ' runs rules with this prefix, so it would never run.';
            }
        }

        return $problems;
    }

    /**
     * A key out of a name: lower case, digits and hyphens.
     *
     * @since  1.3.0
     */
    public static function slug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'generator';
    }
}
