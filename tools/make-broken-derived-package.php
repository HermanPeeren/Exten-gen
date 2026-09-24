<?php

/**
 * Write a derived language that breaks the one it derives from.
 *
 *   php tools/make-broken-derived-package.php   # tests/cypress/fixtures/broken-derived.zip
 *
 * The fixture for the refusal. `AncestryCheck` says a derived language may add
 * and may not remove or rename, and the failure it prevents is silent: a
 * parent's rule selects `Entity`, the child renamed it, the selector returns
 * nothing, every rule fires zero times, and out comes a package with a manifest
 * and a third of its files looking exactly like one that worked.
 *
 * A refusal nobody has watched refuse is a refusal nobody knows works, so this
 * builds the one thing it is supposed to stop.
 *
 * It renames a *feature* rather than a concept, which is the half added last and
 * the more confusing of the two: the concept keys are untouched, so every stored
 * model still resolves. Only the paths move, and a path that walks into nothing
 * renders an empty string into a generated file.
 */

declare(strict_types=1);

\defined('_JEXEC') || \define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';

use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageManifest;
use Yepr\Gen\Core\Package\PackageReader;

$shipped = glob(\dirname(__DIR__) . '/packages/*.zip') ?: [];

if (\count($shipped) !== 1) {
    fwrite(STDERR, 'Expected one shipped language package, found ' . \count($shipped) . ".\n");
    exit(1);
}

$reader = PackageReader::fromZip($shipped[0]);
$parent = $reader->manifest();

$name    = 'BrokenER';
$version = '1.0';
$newRoot = MetalanguagePackage::installRoot($name, $version);

$files = new FileCollection();

foreach ($reader->files() as $path => $contents) {
    if ($path !== MetalanguagePackage::MANIFEST) {
        $files->add($path, str_replace($parent->formRoot, $newRoot, $contents));
    }
}

$model = json_decode($reader->modelJson(), true, 512, JSON_THROW_ON_ERROR);

$model['name']      = $name;
$model['version']   = $version;
$model['dependsOn'] = (object) [
    'dependsOn0' => ['language' => $parent->key . '|' . $parent->version],
];

$files->replace(MetalanguagePackage::MODEL, json_encode(
    $model,
    \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR
) . "\n");

// The break. One feature of one concept, renamed and nothing else: the smallest
// thing the guard should refuse, so a run that imported this would be saying
// the guard does nothing rather than that this package is unusual.
$broken = [];
$renamed = false;

foreach ($parent->concepts as $concept) {
    if (($concept['name'] ?? '') === 'Entity' && isset($concept['features'])) {
        foreach ($concept['features'] as $index => $feature) {
            if ($feature['name'] === 'entity_name') {
                $concept['features'][$index]['name'] = 'title';
                $renamed                             = true;
            }
        }
    }

    $broken[] = $concept;
}

if (!$renamed) {
    fwrite(STDERR, "The shipped package has no Entity.entity_name to rename, so this fixture would prove nothing.\n");
    exit(1);
}

$hashes = [];

foreach ($files as $path => $contents) {
    $hashes[$path] = hash('sha256', $contents);
}

ksort($hashes);

$files->add(MetalanguagePackage::MANIFEST, (new PackageManifest(
    $name,
    $name,
    $version,
    $parent->root,
    $newRoot,
    $parent->language,
    $parent->tag,
    $broken,
    $hashes,
    MetalanguagePackage::FORMAT,
    gmdate('c'),
    [['key' => $parent->key, 'version' => $parent->version]]
))->toJson());

$target = \dirname(__DIR__) . '/tests/cypress/fixtures/broken-derived.zip';

if (!is_dir(\dirname($target))) {
    mkdir(\dirname($target), 0755, true);
}

(new ZipWriter())->write($files, $target);

printf(
    "wrote %s: %s %s, deriving from %s %s and renaming Entity.entity_name (%d files)\n",
    $target,
    $name,
    $version,
    $parent->key,
    $parent->version,
    \count($files)
);
