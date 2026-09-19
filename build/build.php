<?php

/**
 * Assembles the installable component package.
 *
 *   php build/build.php        ->  build/com_extengen-<version>.zip
 *
 * The repository already mirrors the installed layout, so building is mostly
 * copying src/ and leaving out what is a product rather than a source.
 *
 * What this does *not* do yet, and step 1.6 does: ship the media folder and the
 * generator templates that the manifest still omits, carry a copy of the shared
 * library so an install can bring it along, and generate the second manifest
 * rather than keeping two by hand. Until then this produces exactly what the
 * manifest currently describes - no more, and no pretence otherwise.
 */

declare(strict_types=1);

$root     = \dirname(__DIR__);
$manifest = $root . '/src/extengen.xml';

$xml = simplexml_load_file($manifest);

if ($xml === false) {
    fwrite(STDERR, "Cannot read the manifest at {$manifest}.\n");
    exit(1);
}

$version = trim((string) $xml->version);
$element = trim((string) $xml->name);

if ($version === '' || $element === '') {
    fwrite(STDERR, "The manifest needs both a version and a name.\n");
    exit(1);
}

$zipPath = $root . '/build/' . $element . '-' . $version . '.zip';

// Products, not sources: generation output, the Twig compilation cache, and a
// node_modules tree that is only there for one uuid helper.
$skip = [
    'administrator/components/com_extengen/generated/',
    'administrator/components/com_extengen/compilation_cache/',
    'node_modules/',
];

echo "Building {$element} {$version}\n";

if (is_file($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fwrite(STDERR, "Cannot create {$zipPath}.\n");
    exit(1);
}

$added   = 0;
$skipped = 0;

foreach (walk($root . '/src') as $absolute) {
    // Forward slashes always: a backslash in a zip entry becomes a literal
    // backslash in a filename on Linux, and the extension then fails to load.
    $entry = str_replace('\\', '/', substr($absolute, \strlen($root . '/src') + 1));

    foreach ($skip as $prefix) {
        if (str_starts_with($entry, $prefix) || str_contains($entry, '/' . $prefix)) {
            $skipped++;

            continue 2;
        }
    }

    $zip->addFile($absolute, $entry);
    $added++;
}

$zip->close();

printf(
    "\n%s\n  %d files, %s (%d skipped as build products)\n",
    basename($zipPath),
    $added,
    formatSize((int) filesize($zipPath)),
    $skipped
);

/** @return iterable<string> Every file under a directory, in a stable order. */
function walk(string $directory): iterable
{
    if (!is_dir($directory)) {
        return [];
    }

    $files    = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    sort($files, SORT_STRING);

    return $files;
}

function formatSize(int $bytes): string
{
    return $bytes > 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : number_format($bytes / 1024, 1) . ' KB';
}
