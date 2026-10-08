<?php

/**
 * Write two generator packages for the browser specs to import: step 5.4.
 *
 *   php tools/make-test-generator.php   # tests/cypress/fixtures/generator.zip and broken-generator.zip
 *
 * Shaped like what Gen-gen writes - a `generator.json` and the rule file it
 * names - and built here rather than exported from Gen-gen, for the reason
 * `make-test-package.php` gives: these specs are about Exten-gen's import, and
 * should not fail because the other repository changed. Gen-gen's own suite
 * reads its real packages with `GeneratorPackage`, which covers the other half.
 *
 * The good one is this component's own rules without the LICENSE rule, so a
 * project generated with it differs in exactly one visible way: no LICENSE.txt.
 * The broken one names a template the target does not have, which the import
 * must refuse before it stores anything.
 */

declare(strict_types=1);

\defined('_JEXEC') || \define('_JEXEC', 1);

require __DIR__ . '/../vendor/autoload.php';

use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;
use Yepr\Gen\Core\Rule\RuleSet;

$fixtures = __DIR__ . '/../tests/cypress/fixtures';
$rules    = RuleSet::fromFile(RuleDrivenGenerator::defaultRuleFile())->toArray();
$path     = 'administrator/components/com_extengen/src/Generator/Rules/joomla6.rules.json';

/**
 * @param  array<int, array<string, mixed>>  $rules
 */
$write = static function (string $zipPath, string $name, array $rules) use ($path): void {
    $manifest = [
        'format'       => 1,
        'key'          => strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name)),
        'name'         => $name,
        'target'       => 'joomla6',
        'metalanguage' => ['key' => 'ER1', 'version' => ''],
        'rules'        => $path,
        'groups'       => [],
    ];

    if (is_file($zipPath)) {
        unlink($zipPath);
    }

    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "Cannot write {$zipPath}\n");
        exit(1);
    }

    $zip->addFromString('generator.json', json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . "\n");
    $zip->addFromString($path, RuleSet::fromArray($rules)->toJson() . "\n");
    $zip->close();

    echo basename($zipPath) . ': ' . \count($rules) . " rules\n";
};

$withoutLicense = array_values(array_filter(
    $rules,
    static fn (array $rule): bool => ($rule['id'] ?? '') !== 'admin.general.license'
));

$write($fixtures . '/generator.zip', 'Joomla 6 without a licence file', $withoutLicense);

$broken                = $withoutLicense;
$broken[0]['template'] = 'component/no/such/template.twig';

$write($fixtures . '/broken-generator.zip', 'Broken generator', $broken);
