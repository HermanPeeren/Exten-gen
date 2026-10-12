<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a browser save changes that nobody changed, put back.
 *
 * A form post is not a neutral copy of what was on screen. Two things arrive
 * different from what the stored model held, without anybody typing them
 * (the plan's step 6.4):
 *
 * - **Line endings.** A browser posts every line break in a textarea as CRLF.
 *   JCB writes LF, so a value stored with LF came back with CRLF, and one with
 *   no line break and a new line typed into it got CRLF as well.
 * - **Absent becomes a value.** LionWeb tells a property that is not there
 *   from one that holds `''`, and a form cannot: an empty text box posts `''`
 *   and a list posts the option it shows. Hello World came back with 16
 *   properties it did not have as `''`, and 7 as a list's first option.
 *
 * Both are put right against the stored model the save replaces, which is the
 * only place the answer is. The generated form carries no marker for either -
 * no hidden companion field, no data attribute - because a companion field is
 * one more key in the stored model, which `Form::filter()` keeps only when it
 * is declared and the LionWeb export would then have to know is not a feature.
 *
 * **How a posted node finds its stored one.** By `LIonWeb_id` when it has
 * one, which is every node of a model that came from LionWeb. Otherwise by its
 * path from the root - the keys a subform posts are the ones it was rendered
 * with, `field0`, `field1` - which is how the root and every row of an ER1
 * project are matched, since nothing there carries an id. A path only matches
 * a stored node that has no id either, so a new row in a LionWeb model (its
 * hidden id is empty) matches nothing, and is new.
 *
 * @since  1.6.6
 */
final class BrowserSave
{
    /**
     * The prefix of the keys a stored model reserves for itself.
     *
     * `LIonWeb_key`, `LIonWeb_id`, `LIonWeb_language` and
     * `LIonWeb_languageVersion`: hidden fields, never a feature, and never
     * anything to put back.
     *
     * @since  1.6.6
     */
    private const RESERVED = 'LIonWeb_';

    /**
     * Give every posted string the line endings its stored value had.
     *
     * For each string property, against the same property of the stored node:
     *
     * 1. Posted without `\r\n`: kept as posted.
     * 2. Stored with `\r\n`: kept as posted. It was CRLF and comes back CRLF.
     * 3. Otherwise - stored with LF only, stored without a line break, or a
     *    property or node that is new - every `\r\n` becomes `\n`. LF is what
     *    JCB writes and what a new value should be.
     * 4. Joomla's textarea layout writes the value straight after `>`, and
     *    HTML drops one newline there, so a value that began with a line break
     *    comes back without it. When the stored value is the result of the
     *    above with `\n` or `\r\n` in front, the stored value is kept. None of
     *    the demo blueprint's values begins with one: a guard, not a repair
     *    anybody has needed.
     *
     * A value that mixed CRLF and LF cannot be told apart once posted, and
     * comes back all CRLF (rule 2). None of the blueprint's do.
     *
     * A text box cannot carry a line break at all, so there is no need to know
     * which control a value came from: from a text box, rule 1 applies.
     *
     * @param  array<array-key, mixed>  $posted  The model as posted, after filtering.
     * @param  array<array-key, mixed>  $stored  The model the save replaces; empty for a new project.
     *
     * @return array<array-key, mixed>  The posted model, line endings restored.
     *
     * @since  1.6.6
     */
    public static function lineEndings(array $posted, array $stored): array
    {
        return self::restoreLineEndings($posted, '', self::index($stored, ''));
    }

    /**
     * Leave a property absent that was absent and was not filled in.
     *
     * Only on a node matched by `LIonWeb_id`: a model that came from LionWeb,
     * where absent and `''` are two answers and the export writes them as
     * two. An ER1 project never had the distinction - every screen save
     * posted every field - and a project made on screen is saved once with no
     * model in it at all, so in a model matched by path *every* property was
     * absent the first time, and keeping them absent would store nothing.
     *
     * A property absent from the stored node that comes back holding exactly
     * what the form shows for an absent one - `''`, a field's `default`, or a
     * list's first option - stays absent. `$shown` says the non-empty ones,
     * per concept, as `Metalanguages::shownWhenAbsent()` reads them off the
     * forms; anything it does not name shows `''`.
     *
     * **The limit, said plainly.** On screen the two cannot be told apart, so
     * somebody who wants `''` - or the first option - on a property that was
     * absent does not get it: the property stays absent. Typing something
     * else, saving, and clearing it again does get it, because the property
     * is then present. Exporting a value nobody chose is the worse of the two.
     *
     * @param  array<array-key, mixed>  $posted  The model as posted, after filtering.
     * @param  array<array-key, mixed>  $stored  The model the save replaces; empty for a new project.
     * @param  array<string, array<string, string>>  $shown  Concept key => property name => value shown when absent.
     *
     * @return array<array-key, mixed>  The posted model, absent properties left out.
     *
     * @since  1.6.6
     */
    public static function absentKept(array $posted, array $stored, array $shown): array
    {
        return self::keepAbsent($posted, self::index($stored, ''), $shown);
    }

    /**
     * Every node of a stored model, by id or else by path.
     *
     * @param  array<array-key, mixed>  $node
     *
     * @return array<string, array<array-key, mixed>>
     */
    private static function index(array $node, string $path): array
    {
        if ($node === []) {
            return [];
        }

        $index = [self::matchKey($node, $path) => $node];

        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $index += self::index($value, $path . '/' . $key);
            }
        }

        return $index;
    }

    /**
     * What a node is found by: its id, or its path when it has none.
     *
     * @param  array<array-key, mixed>  $node
     */
    private static function matchKey(array $node, string $path): string
    {
        $id = self::id($node);

        return $id !== '' ? 'id:' . $id : 'path:' . $path;
    }

    /**
     * @param  array<array-key, mixed>  $node
     */
    private static function id(array $node): string
    {
        $id = $node[self::RESERVED . 'id'] ?? '';

        return \is_string($id) ? $id : '';
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<string, array<array-key, mixed>>  $index
     *
     * @return array<array-key, mixed>
     */
    private static function restoreLineEndings(array $node, string $path, array $index): array
    {
        $stored = $index[self::matchKey($node, $path)] ?? [];

        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $node[$key] = self::restoreLineEndings($value, $path . '/' . $key, $index);

                continue;
            }

            if (!\is_string($value) || str_starts_with((string) $key, self::RESERVED)) {
                continue;
            }

            $was = $stored[$key] ?? null;

            $node[$key] = self::lineEnding($value, \is_string($was) ? $was : null);
        }

        return $node;
    }

    /**
     * One value, by the four rules `lineEndings()` sets out.
     */
    private static function lineEnding(string $posted, ?string $stored): string
    {
        $kept = $posted;

        if (str_contains($posted, "\r\n") && ($stored === null || !str_contains($stored, "\r\n"))) {
            $kept = str_replace("\r\n", "\n", $posted);
        }

        if ($stored !== null && ($stored === "\n" . $kept || $stored === "\r\n" . $kept)) {
            return $stored;
        }

        return $kept;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  array<string, array<array-key, mixed>>  $index
     * @param  array<string, array<string, string>>  $shown
     *
     * @return array<array-key, mixed>
     */
    private static function keepAbsent(array $node, array $index, array $shown): array
    {
        $id     = self::id($node);
        $stored = $id !== '' ? ($index['id:' . $id] ?? null) : null;
        $marker = $node[self::RESERVED . 'key'] ?? '';
        $asShown = $shown[\is_string($marker) ? $marker : ''] ?? [];

        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $node[$key] = self::keepAbsent($value, $index, $shown);

                continue;
            }

            if (
                $stored === null
                || !\is_string($value)
                || str_starts_with((string) $key, self::RESERVED)
                || \array_key_exists($key, $stored)
            ) {
                continue;
            }

            if ($value === ($asShown[$key] ?? '')) {
                unset($node[$key]);
            }
        }

        return $node;
    }
}
