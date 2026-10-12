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
 * Whether a project's form arrived whole.
 *
 * PHP reads at most `max_input_vars` variables out of a post - 1000 unless
 * somebody raised it - and drops the rest without telling the application.
 * JCB's demo blueprint, imported through LionWeb, puts about 24,100 inputs on
 * the edit screen. So the save posted a quarter of a model, the token and the
 * task were among what was dropped, and Joomla showed the projects list with
 * no message at all: nothing was saved, and nothing said so.
 *
 * The edit layout therefore puts the task and the token *first*, so that they
 * survive, and a hidden field named here *last*, so that it is the first thing
 * to go. A save whose post lacks it is refused by `ProjectModel::validate()`
 * with the numbers, before anything is stored - a truncated post saved is a
 * model with three quarters of it missing.
 *
 * A browser script fills in how many inputs the form held, in a field near the
 * top, so the message can say how far over the limit it was. Without it the
 * message still names the limit.
 *
 * This is the safety net. The fix is not posting 24,000 fields from one
 * screen at all - the plan's step 6.5.
 *
 * @since  1.6.5
 */
final class PostLimit
{
    /**
     * The field the layout puts last. Missing means the post was cut short.
     *
     * @since  1.6.5
     */
    public const SENTINEL = 'extengen_complete';

    /**
     * The field the browser fills with the number of inputs the form held.
     *
     * @since  1.6.5
     */
    public const COUNT = 'extengen_inputs';

    /**
     * Whether the post reached the field the layout puts last.
     *
     * @param  array<string, mixed>  $post  The request's POST variables.
     *
     * @since  1.6.5
     */
    public static function isComplete(array $post): bool
    {
        return isset($post[self::SENTINEL]);
    }

    /**
     * How many variables PHP read out of the post.
     *
     * Leaves, not keys: `jform[a][b]` and `jform[a][c]` are two variables as
     * `max_input_vars` counts them, and one key at the top.
     *
     * @param  array<mixed>  $post
     *
     * @since  1.6.5
     */
    public static function received(array $post): int
    {
        $count = 0;

        array_walk_recursive($post, static function () use (&$count): void {
            $count++;
        });

        return $count;
    }

    /**
     * How many inputs the browser said the form held, or 0 when it did not say.
     *
     * @param  array<string, mixed>  $post
     *
     * @since  1.6.5
     */
    public static function sent(array $post): int
    {
        return max(0, (int) ($post[self::COUNT] ?? 0));
    }
}
