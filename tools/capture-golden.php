<?php

/**
 * Rewrites the approved output for a golden fixture.
 *
 *   php tools/capture-golden.php            # every fixture
 *   php tools/capture-golden.php eventschedule
 *   php tools/capture-golden.php balloonledger joomla6-ledgerlang
 *
 * The fixtures for the Joomla 6 generator are in `tests/Fixtures/golden`. Those
 * for a generator that extends it (step 5.9) are in
 * `tests/Fixtures/golden-<generator id>`, generated with that generator's rules:
 * its own laid over its parents'.
 *
 * Run it deliberately, and read the diff. The golden files are the review
 * surface for every change to a template or a generator, and accepting one
 * without looking is the one way this practice fails.
 *
 * Until step 1.4 this captures what *today's* generators produce, bugs
 * included. That is the point: it is a baseline to port against, not a
 * statement that the output is right.
 */

declare(strict_types=1);

// The suite's bootstrap, not the autoloader on its own: a Joomla source
// file opens with `defined('_JEXEC') or die`, so loading one without that
// constant ends the script with no output and no error at all.
require __DIR__ . '/../tests/bootstrap.php';

use Yepr\Component\Extengen\Tests\Support\GenerationHarness;
use Yepr\Gen\Core\Testing\GoldenFiles;

$generator = $argv[2] ?? 'joomla6';
$fixtures  = new GoldenFiles(__DIR__ . '/../tests/Fixtures/golden' . ($generator === 'joomla6' ? '' : '-' . $generator));
$names     = isset($argv[1]) && $argv[1] !== '' ? [$argv[1]] : $fixtures->names();

if ($names === []) {
    fwrite(STDERR, "No fixture models found.\n");
    exit(1);
}

foreach ($names as $name) {
    $ast     = json_decode($fixtures->model($name), false, 512, JSON_THROW_ON_ERROR);
    $files   = $generator === 'joomla6'
        ? GenerationHarness::run($ast)
        : GenerationHarness::runWithGenerator($ast, $generator);
    $written = $fixtures->write($name, $files);

    printf("%s: %d file(s)\n", $name, \count($written));
}

echo "\nRead the diff before committing.\n";
