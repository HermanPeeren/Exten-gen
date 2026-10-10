<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Testing\GoldenFiles;
use Yepr\Gen\Core\Testing\GoldenTestCase;

/**
 * The LedgerLang generator's output, approved file by file: step 5.9.
 *
 * BalloonLedger is the project Meta-gen ships with LedgerLang, copied here as
 * it is. Generated with the LedgerLang generator - the Joomla 6 generator's
 * rules with three of its own added - it gives the component the ER1 generator
 * gives, plus the account roles and the posting rules as PHP.
 *
 * Refresh with `php tools/capture-golden.php balloonledger joomla6-ledgerlang`,
 * and read the diff.
 */
final class LedgerLangGoldenTest extends GoldenTestCase
{
    protected function fixtures(): GoldenFiles
    {
        return new GoldenFiles(\dirname(__DIR__) . '/Fixtures/golden-joomla6-ledgerlang');
    }

    protected function generate(string $name): FileCollection
    {
        $ast = json_decode($this->fixtures()->model($name), false, 512, JSON_THROW_ON_ERROR);

        return GenerationHarness::runWithGenerator($ast, 'joomla6-ledgerlang');
    }
}
