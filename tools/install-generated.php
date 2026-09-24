<?php

/**
 * Install a component Exten-gen generated, into the site it generated it on.
 *
 *   php tools/install-generated.php BalloonPlanning
 *   php tools/install-generated.php BalloonPlanning ../site
 *
 * Generation leaves a package under the component's own `generated/` folder,
 * named for the version in the model. This finds the newest one and hands it
 * to Joomla's CLI installer, so a spec can check what a generated component
 * does on a real site rather than what its bytes look like.
 */

declare(strict_types=1);

$root    = \dirname(__DIR__);
$project = $argv[1] ?? null;
$site    = $argv[2] ?? $root . '/joomla';

if ($project === null) {
    fwrite(STDERR, "Usage: php tools/install-generated.php <ProjectName> [site]\n");
    exit(1);
}

$directory = $site . '/administrator/components/com_extengen/generated/' . $project;

// Under the target's own directory since 4.4, because a second target writes a
// zip too and `glob('*.zip')` over both would hand Joomla a WordPress plugin.
// The bare directory is still read, for output generated before that.
$packages = glob($directory . '/joomla6/*.zip') ?: (glob($directory . '/*.zip') ?: []);

if ($packages === []) {
    fwrite(STDERR, "No generated package under {$directory}. Generate the project first.\n");
    exit(1);
}

// Newest wins: generating again leaves the previous version's package beside
// the new one, and installing the older of the two is a confusing way to fail.
usort($packages, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

$cli = $site . '/cli/joomla.php';

if (!is_file($cli)) {
    fwrite(STDERR, "No Joomla CLI at {$cli}.\n");
    exit(1);
}

printf("installing %s\n", basename($packages[0]));

passthru(
    \sprintf('php %s extension:install --path=%s', escapeshellarg($cli), escapeshellarg($packages[0])),
    $status
);

exit($status);
