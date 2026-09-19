<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use Yepr\Component\Extengen\Tests\Support\LegacyGeneratorRunner;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Testing\GoldenFiles;
use Yepr\Gen\Core\Testing\GoldenTestCase;

/**
 * What the generators produce today, pinned byte for byte.
 *
 * A baseline, not an endorsement. Step 1.4 moves generation onto the shared
 * Pipeline, and the whole value of this is that the move can be done as a diff:
 * anything that changes is either an improvement somebody can see, or a
 * regression that would otherwise have shipped.
 *
 * So the approved output is deliberately captured **with its current bugs in
 * it**. When one is fixed, the diff shows the fix, which is the right moment to
 * look at it.
 *
 * The comparison, the fixture discovery and the both-directions checking all
 * come from the shared library - this class is the two questions it asks.
 */
final class GoldenOutputTest extends GoldenTestCase
{
    protected function fixtures(): GoldenFiles
    {
        return new GoldenFiles(\dirname(__DIR__) . '/Fixtures/golden');
    }

    protected function generate(string $name): FileCollection
    {
        $ast = json_decode($this->fixtures()->model($name), false, 512, JSON_THROW_ON_ERROR);

        return LegacyGeneratorRunner::run($ast);
    }
}
