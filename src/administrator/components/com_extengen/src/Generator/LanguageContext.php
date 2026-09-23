<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator;

use Yepr\Gen\Core\Reference\ReferenceIndex;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Which language the project being generated is written in: step 3.6.
 *
 * A selector that follows a reference needs the language's reference table to
 * follow it with, and the generators are built by the target with a fixed
 * signature - renderer and language strings - so there is nowhere to hand one
 * in. `VocabularyContext` and `MetalanguageContext` in Gen-gen are the same
 * seam for the same reason, and this is the third: chosen once at the top of a
 * run, needed by something several layers down, small enough to name and
 * resettable enough to test.
 *
 * **Null is a real state.** Gen-gen's acceptance check runs this component's
 * pipeline directly, without going through the screen that sets this, and it
 * should keep working - so a caller that has not said falls back to ER1's
 * table, which is what every project here is written in anyway.
 *
 * @since  1.2.0
 */
final class LanguageContext
{
    /**
     * @var    ?ReferenceIndex
     * @since  1.2.0
     */
    private static ?ReferenceIndex $references = null;

    /**
     * Say which language's table the run should follow references with.
     *
     * @since  1.2.0
     */
    public static function use(?ReferenceIndex $references): void
    {
        self::$references = $references;
    }

    /**
     * The table, or null when nobody said.
     *
     * Impure, and it says so: an analyser that assumes a static call answers
     * the same twice in one scope concludes the second is null because the
     * first was, which is backwards for a method whose job is to read what
     * somebody just set.
     *
     * @phpstan-impure
     *
     * @since  1.2.0
     */
    public static function current(): ?ReferenceIndex
    {
        return self::$references;
    }

    /**
     * Forget it, so one run cannot decide what the next one follows.
     *
     * @since  1.2.0
     */
    public static function reset(): void
    {
        self::$references = null;
    }
}
