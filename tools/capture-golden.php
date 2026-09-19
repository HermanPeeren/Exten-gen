<?php

/**
 * Rewrites the approved output for a golden fixture.
 *
 *   php tools/capture-golden.php            # every fixture
 *   php tools/capture-golden.php eventschedule
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

require __DIR__ . '/../vendor/autoload.php';

use Yepr\Component\Extengen\Tests\Support\LegacyGeneratorRunner;
use Yepr\Gen\Core\Testing\GoldenFiles;

$fixtures = new GoldenFiles(__DIR__ . '/../tests/Fixtures/golden');
$names    = isset($argv[1]) ? [$argv[1]] : $fixtures->names();

if ($names === []) {
    fwrite(STDERR, "No fixture models found.\n");
    exit(1);
}

foreach ($names as $name) {
    $ast     = json_decode($fixtures->model($name), false, 512, JSON_THROW_ON_ERROR);
    $files   = LegacyGeneratorRunner::run($ast);
    $written = $fixtures->write($name, $files);

    printf("%s: %d file(s)\n", $name, \count($written));
}

echo "\nRead the diff before committing.\n";
