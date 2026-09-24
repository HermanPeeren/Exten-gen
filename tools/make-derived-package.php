<?php

/**
 * Write a metalanguage package that derives from the ER1 this component ships.
 *
 *   php tools/make-derived-package.php       # tests/cypress/fixtures/derived.zip
 *
 * Step 4.5's fixture. A language may declare `dependsOn`, and a project written
 * in such a language may be generated from by the generators written for its
 * parent - which is a claim about a whole site and therefore a claim only the
 * browser can check.
 *
 * **Built from ER1's own package rather than by hand**, which is the opposite
 * of what `make-test-package.php` does and for a reason that does not apply
 * there. Testlang exists to be *unlike* ER1: a small language of its own, whose
 * forms prove a project opens with what the package carried. This one has to be
 * a genuine superset of ER1 or the guard refuses it, and hand-writing twenty-six
 * forms that match ER1's concept keys would be hand-maintaining a copy of ER1.
 *
 * So it reads the shipped package, renames it, and says what it derives from.
 * Every concept key survives, because they are ER1's own - which is exactly
 * what `AncestryCheck` is going to ask about.
 *
 * It adds no concept of its own. A derived language that adds nothing is still
 * a derived language, and what these specs are about is whether the ancestry is
 * walked - not whether an addition is ignored, which is a property of reading
 * named paths and was true before any of this.
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

$reader   = PackageReader::fromZip($shipped[0]);
$problems = $reader->problems();

if ($problems !== []) {
    fwrite(STDERR, "The shipped package is not readable:\n  " . implode("\n  ", $problems) . "\n");
    exit(1);
}

$parent = $reader->manifest();

$name    = 'DerivedER';
$version = '1.0';
$oldRoot = $parent->formRoot;
$newRoot = MetalanguagePackage::installRoot($name, $version);

$files = new FileCollection();

// Every file, with the install root rewritten inside it: a subform's
// `formsource` is resolved against the site root, so a form copied without this
// would quietly load the parent's file instead of this language's - two
// languages sharing one form until somebody edited it.
foreach ($reader->files() as $path => $contents) {
    if ($path === MetalanguagePackage::MANIFEST) {
        continue;
    }

    $files->add($path, str_replace($oldRoot, $newRoot, $contents));
}

// The model says what it is, and what it is built on. The forms it carries are
// ER1's, which is the point: a derived language is its parent plus whatever it
// adds, and this one adds nothing.
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
    // ER1's own, unchanged. A child that renamed one of these is what
    // `AncestryCheck` refuses, and `make-broken-derived-package.php` is the
    // fixture for that.
    $parent->concepts,
    $hashes,
    MetalanguagePackage::FORMAT,
    gmdate('c'),
    [['key' => $parent->key, 'version' => $parent->version]]
))->toJson());

$target = \dirname(__DIR__) . '/tests/cypress/fixtures/derived.zip';

if (!is_dir(\dirname($target))) {
    mkdir(\dirname($target), 0755, true);
}

(new ZipWriter())->write($files, $target);

printf(
    "wrote %s: %s %s deriving from %s %s (%d files)\n",
    $target,
    $name,
    $version,
    $parent->key,
    $parent->version,
    \count($files)
);
