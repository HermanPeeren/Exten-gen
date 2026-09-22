<?php

/**
 * Write a small metalanguage package for the browser specs to import.
 *
 *   php tools/make-test-package.php            # tests/cypress/fixtures/metalanguage.zip
 *
 * Built here rather than committed as a binary, and built by hand rather than
 * taken from Meta-gen. Both are deliberate.
 *
 * A committed zip is a fixture nobody can read in a diff, and one that goes
 * stale the day the format changes with nothing to say so. A zip exported from
 * Meta-gen would make this repository's specs fail when *that* repository
 * changed something these specs are not about - Exten-gen depends on the
 * package format, which is the library's, not on Meta-gen's generator.
 */

declare(strict_types=1);

\defined('_JEXEC') || \define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';

use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageManifest;

$name    = 'Testlang';
$version = '1.0';
$root    = MetalanguagePackage::installRoot($name, $version);

$files = new FileCollection();

$files->add(MetalanguagePackage::MODEL, json_encode(
    ['name' => $name, 'version' => $version],
    \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR
) . "\n");

// A root classifier with one field and one repeating child, which is enough
// shape for a person to see that the screen came from the package.
$files->add(
    MetalanguagePackage::formPath('Thing'),
    '<?xml version="1.0" encoding="utf-8"?>' . "\n"
    . '<form><fieldset name="entities" addfieldprefix="Yepr\Gen\Joomla\Form\Field">'
    . '<field name="thingName" type="text" label="YEPR_TESTLANG_THING_FIELD_THINGNAME_LABEL" />'
    . '<field name="parts" type="subform" multiple="true" buttons="add,remove"'
    . ' layout="joomla.form.field.subform.repeatable"'
    . ' formsource="' . $root . MetalanguagePackage::formPath('Part') . '"'
    . ' label="YEPR_TESTLANG_THING_FIELD_PARTS_LABEL" id="parts" />'
    . '</fieldset></form>' . "\n"
);

$files->add(
    MetalanguagePackage::formPath('Part'),
    '<?xml version="1.0" encoding="utf-8"?>' . "\n"
    . '<form><fieldset addfieldprefix="Yepr\Gen\Joomla\Form\Field">'
    . '<field name="partName" type="text" label="YEPR_TESTLANG_PART_FIELD_PARTNAME_LABEL" />'
    . '</fieldset></form>' . "\n"
);

$files->add(MetalanguagePackage::REFERENCES, "{}\n");

$files->add(
    MetalanguagePackage::languagePath($name),
    "; Testlang 1.0\n"
    . "YEPR_TESTLANG_THING_FIELD_THINGNAME_LABEL=\"What this thing is called\"\n"
    . "YEPR_TESTLANG_THING_FIELD_PARTS_LABEL=\"Parts\"\n"
    . "YEPR_TESTLANG_PART_FIELD_PARTNAME_LABEL=\"Part name\"\n"
);

$hashes = [];

foreach ($files as $path => $contents) {
    $hashes[$path] = hash('sha256', $contents);
}

ksort($hashes);

$files->add(MetalanguagePackage::MANIFEST, (new PackageManifest(
    $name,
    $name,
    $version,
    'Thing',
    $root,
    MetalanguagePackage::languagePath($name),
    MetalanguagePackage::TAG,
    $hashes,
    MetalanguagePackage::FORMAT,
    gmdate('c')
))->toJson());

$target = \dirname(__DIR__) . '/tests/cypress/fixtures/metalanguage.zip';

if (!is_dir(\dirname($target))) {
    mkdir(\dirname($target), 0755, true);
}

(new ZipWriter())->write($files, $target);

echo 'wrote ', $target, ' (', \count($files), " files)\n";
