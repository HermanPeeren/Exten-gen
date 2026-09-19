<?php

/**
 * Writes the Joomla 6 target's vocabulary descriptor.
 *
 *   php build/vocabulary.php
 *
 * A rule set names selectors, derivations and templates. Which of those exist
 * is this component's business, and it is recorded in PHP: two registries and a
 * directory of Twig files. That is exactly what the engine needs and exactly
 * what an editor cannot use - Gen-gen has to offer the choices without loading
 * a line of Exten-gen.
 *
 * So the lists are published as data, next to the rule file they constrain.
 * Generated rather than maintained, for the reason updates.xml is generated: a
 * list kept in two places disagrees with itself, and here the disagreement is a
 * form offering a derivation nobody registered.
 *
 * `VocabularyTest` fails when the committed file is not what this writes.
 */

declare(strict_types=1);

require_once \dirname(__DIR__) . '/tests/bootstrap.php';

use Yepr\Component\Extengen\Administrator\Generator\Rules\Joomla6Derivations;
use Yepr\Component\Extengen\Administrator\Generator\Rules\Joomla6Selectors;
use Yepr\Component\Extengen\Administrator\Generator\Target\Joomla6Target;
use Yepr\Gen\Core\Rule\Vocabulary;

$root      = \dirname(__DIR__);
$component = $root . '/src/administrator/components/com_extengen';

$target    = new Joomla6Target($component . '/generator_templates');
$templates = [];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target->templateSetRoot(), FilesystemIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if (!$file->isFile()) {
        continue;
    }

    // Identifiers as a rule spells them: relative to the template set, forward
    // slashes whatever the platform uses.
    $templates[] = str_replace(
        DIRECTORY_SEPARATOR,
        '/',
        substr($file->getPathname(), \strlen($target->templateSetRoot()) + 1)
    );
}

$vocabulary = Vocabulary::fromRegistries(
    $target->id(),
    Joomla6Selectors::registry(),
    (new Joomla6Derivations())->registry(),
    $templates
);

$path = $component . '/src/Generator/Rules/joomla6.vocabulary.json';

file_put_contents($path, $vocabulary->toJson());

printf(
    "vocabulary written for %s: %d selectors, %d derivations, %d templates\n",
    $vocabulary->target,
    \count($vocabulary->selectors),
    \count($vocabulary->derivations),
    \count($vocabulary->templates)
);
